<?php
// Simple AI cron wrapper: call the AI service endpoints and persist basic notifications
// Intended to be run from Task Scheduler or cron.

date_default_timezone_set('UTC');
$base = 'http://127.0.0.1:5000';
$logfile = __DIR__ . '/ai_service.log';

function call_api($url) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$res = curl_exec($ch);
	$err = curl_error($ch);
	curl_close($ch);
	if ($err) return ['error' => $err];
	$decoded = json_decode($res, true);
	return $decoded ?? ['raw' => $res];
}

$pred = call_api($base . '/api/blood-predictions');
file_put_contents($logfile, date('c') . " - predictions: " . json_encode($pred) . PHP_EOL, FILE_APPEND);

$demand = call_api($base . '/api/demand-analysis');
file_put_contents($logfile, date('c') . " - demand: " . json_encode($demand) . PHP_EOL, FILE_APPEND);

$an = call_api($base . '/api/anomaly-detection');
file_put_contents($logfile, date('c') . " - anomalies: " . json_encode($an) . PHP_EOL, FILE_APPEND);

// Forecast inventory for next 7 days
$fc = call_api($base . '/api/forecast-inventory?days=1');
file_put_contents($logfile, date('c') . " - forecast: " . json_encode($fc) . PHP_EOL, FILE_APPEND);

// Optionally: if predictions show critical shortages, write notifications to DB
require_once __DIR__ . '/../includes/db.php';
if (is_array($pred) && !empty($pred['predictions'])) {
	// Persist predictions into ai_predictions (if table exists) and optionally propose transfers
	foreach ($pred['predictions'] as $p) {
		// persist prediction
		$prediction_json = json_encode($p);
		if ($prediction_json) {
			$ins = $conn->prepare("INSERT INTO ai_predictions (hospital_id, target_date, blood_type, prediction, confidence, horizon_days, created_at) VALUES (NULL, NULL, ?, ?, ?, NULL, NOW())");
			if ($ins) {
				$conf = isset($p['confidence_level']) ? floatval($p['confidence_level']) : null;
				$ins->bind_param('sss', $p['blood_type'], $prediction_json, $p['confidence']);
				$ins->execute();
			}
		}

		// create notification and proposed transfer for high confidence shortage
		if (isset($p['confidence_level']) && $p['confidence_level'] >= 80 && strpos($p['status'], 'Shortage') !== false) {
			$msg = "Predicted shortage for {$p['blood_type']}: status={$p['status']}, confidence={$p['confidence']}";
			// insert notification (pending approval)
            $stmt = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, priority, status, created_at) VALUES ('bloodbank', 0, ?, ?, 'high', 'pending', NOW())");
            if (!$stmt) {
                // fallback if status column doesn't exist
                $stmt = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, priority, created_at) VALUES ('bloodbank', 0, ?, ?, 'high', NOW())");
            }
            if ($stmt) {
                $stmt->bind_param('ss', $msg, $p['blood_type']);
                $stmt->execute();
            }

				// propose a transfer by inserting into blood_transfers as 'automatic' and status 'requested'
				// We don't know which blood_bank to pull from, so create a generic proposal with blood_bank_id=0 and hospital_id=0 (admin to assign)
				$qty = 450; // default unit — you can adjust logic to compute quantity based on predicted shortfall
				$tr = $conn->prepare("INSERT INTO blood_transfers (blood_bank_id, hospital_id, blood_type, quantity_ml, request_type, status, transfer_date, notes, proposal_origin, proposed_by, created_at) VALUES (0, 0, ?, ?, 'automatic', 'requested', CURDATE(), ?, 'ml_service', 'ml_service', NOW())");
				if ($tr) {
					$notes = $msg;
					$tr->bind_param('sis', $p['blood_type'], $qty, $notes);
					$tr->execute();
				}
		}
	}

	// Persist anomalies into ai_anomalies
	if (is_array($an) && !empty($an['anomalies'])) {
		foreach ($an['anomalies'] as $a) {
			$atype = isset($a['type']) ? $a['type'] : ($a['anomaly_type'] ?? 'anomaly');
			$details = json_encode($a);
			$score = isset($a['z_score']) ? floatval($a['z_score']) : (isset($a['score']) ? floatval($a['score']) : null);
			$ins = $conn->prepare("INSERT INTO ai_anomalies (anomaly_type, details, score, created_at) VALUES (?,?,?,NOW())");
			if ($ins) {
				$ins->bind_param('ssd', $atype, $details, $score);
				$ins->execute();
			}
		}
	}

	// Persist forecasts and create notifications/proposals when forecasted low
	if (is_array($fc) && !empty($fc['forecasts'])) {
		// $fc['forecasts'] expected as map blood_type -> predicted_quantity
		foreach ($fc['forecasts'] as $bt => $pred_qty) {
			$pred_qty = intval($pred_qty);
			// persist into ai_forecasts: horizon_days=7, forecast_date = CURDATE()
			$insf = $conn->prepare("INSERT INTO ai_forecasts (hospital_id, blood_type, forecast_date, horizon_days, predicted_quantity_ml, created_at) VALUES (NULL, ?, CURDATE(), 7, ?, NOW())");
			if ($insf) {
				$insf->bind_param('si', $bt, $pred_qty);
				$insf->execute();
			}

			// If predicted quantity below critical threshold, write notification and propose transfer
			if (defined('CRITICAL_THRESHOLD')) {
				$crit = CRITICAL_THRESHOLD;
			} else {
				$crit = 500; // fallback
			}
			if ($pred_qty < $crit) {
				$msg = "Forecast low for $bt: predicted $pred_qty ml (threshold $crit)";
				$stmt = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, priority, status, created_at) VALUES ('hospital', 0, ?, ?, 'high', 'pending', NOW())");
				if (!$stmt) {
					$stmt = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, priority, created_at) VALUES ('hospital', 0, ?, ?, 'high', NOW())");
				}
				if ($stmt) {
					$stmt->bind_param('ss', $msg, $bt);
					$stmt->execute();
				}

				// propose transfer row (admin must assign blood_bank_id & hospital_id if needed)
				$qty = 450;
				$tr = $conn->prepare("INSERT INTO blood_transfers (blood_bank_id, hospital_id, blood_type, quantity_ml, request_type, status, transfer_date, notes, proposal_origin, proposed_by, created_at) VALUES (0, 0, ?, ?, 'automatic', 'requested', CURDATE(), ?, 'ml_service', 'ml_service', NOW())");
				if ($tr) {
					$notes = $msg;
					$tr->bind_param('sis', $bt, $qty, $notes);
					$tr->execute();
				}
			}
		}
	}
}

// --- Auto-create hospital requests from ML forecasts/predictions ---
// Feature flag; set to true to enable.
if (!defined('ENABLE_AUTO_HOSPITAL_REQUESTS')) {
    define('ENABLE_AUTO_HOSPITAL_REQUESTS', true);
}
if (!defined('CRITICAL_THRESHOLD')) {
    define('CRITICAL_THRESHOLD', 500); // ml; fallback if not set elsewhere
}

if (ENABLE_AUTO_HOSPITAL_REQUESTS) {
    // Ensure DB connection is available
    require_once __DIR__ . '/../includes/db.php';

    // Helper: get a default hospital id (min id) if you don't have per-hospital signals
    $default_hospital_id = 0;
    try {
        $qr = $conn->query("SELECT MIN(id) AS hid FROM hospitals");
        if ($qr && ($rw = $qr->fetch_assoc()) && !empty($rw['hid'])) {
            $default_hospital_id = (int)$rw['hid'];
        }
    } catch (Exception $e) {}

    // If still zero, skip automation for safety
    if ($default_hospital_id > 0) {
        // Cooldown-based creator with dedup + audit logging
        $create_auto_request = function($hospital_id, $blood_type, $severity, $confidence, $reason) use ($conn) {
            // Skip invalid args
            if (!$hospital_id || !$blood_type) return null;

            // Dedup within 12h for same hospital+blood_type and active status
            $chk = $conn->prepare("SELECT id FROM blood_requests WHERE hospital_id = ? AND blood_type = ? AND status IN ('pending','approved') AND created_at >= (NOW() - INTERVAL 12 HOUR) LIMIT 1");
            if ($chk) {
                $chk->bind_param('is', $hospital_id, $blood_type);
                $chk->execute();
                $exists = $chk->get_result()->fetch_assoc();
                if ($exists) return null;
            }

            // Severity -> urgency, quantity
            $urgency = 'routine';
            $qty = 450;
            if ($severity === 'high') { $urgency = 'emergency'; $qty = 1350; }
            elseif ($severity === 'medium') { $urgency = 'urgent'; $qty = 900; }

            $notes = sprintf(
                'Auto-generated by ML cron. Severity=%s, Confidence=%s. Reason: %s',
                $severity,
                $confidence !== null ? number_format((float)$confidence, 2) : 'N/A',
                $reason
            );

            $ins = $conn->prepare("INSERT INTO blood_requests (hospital_id, blood_type, quantity_ml, urgency, patient_name, notes, status, created_at, updated_at) VALUES (?,?,?,?, NULL, ?, 'pending', NOW(), NOW())");
            if ($ins) {
                $ins->bind_param('isiss', $hospital_id, $blood_type, $qty, $urgency, $notes);
                if ($ins->execute()) {
                    $req_id = (int)$conn->insert_id;
                    // Audit log if table exists
                    try {
                        $conn->query("CREATE TABLE IF NOT EXISTS ai_auto_request_log (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  hospital_id INT NOT NULL,\n  blood_type VARCHAR(4) NOT NULL,\n  reason TEXT NOT NULL,\n  severity VARCHAR(20) NOT NULL,\n  confidence DECIMAL(6,3) NULL,\n  quantity_ml INT NOT NULL,\n  created_request_id INT NULL,\n  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  INDEX idx_hosp_type (hospital_id, blood_type, created_at)\n)");
                    } catch (Exception $e) {}
                    $alog = $conn->prepare("INSERT INTO ai_auto_request_log (hospital_id, blood_type, reason, severity, confidence, quantity_ml, created_request_id, created_at) VALUES (?,?,?,?,?,?,?, NOW())");
                    if ($alog) {
                        $conf = ($confidence !== null) ? (float)$confidence : null;
                        $alog->bind_param('isssdii', $hospital_id, $blood_type, $reason, $severity, $conf, $qty, $req_id);
                        $alog->execute();
                    }

                    // Notify hospital (optional)
                    $msg = "Auto-request created for $blood_type ($urgency). $reason";
                    $notif = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, urgency_level, status, created_at, read_status, action_taken) VALUES ('hospital', ?, ?, ?, ?, 'pending', NOW(), 0, 0)");
                    if (!$notif) {
                        $notif = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, urgency_level, created_at, read_status, action_taken) VALUES ('hospital', ?, ?, ?, ?, NOW(), 0, 0)");
                    }
                    if ($notif) {
                        $urg = $severity === 'high' ? 'high' : ($severity === 'medium' ? 'medium' : 'low');
                        $notif->bind_param('isss', $hospital_id, $msg, $blood_type, $urg);
                        $notif->execute();
                    }

                    return $req_id;
                }
            }
            return null;
        };

        // Use forecast signals
        if (is_array($fc) && !empty($fc['forecasts'])) {
            foreach ($fc['forecasts'] as $bt => $pred_qty) {
                $pred_qty = intval($pred_qty);
                $crit = defined('CRITICAL_THRESHOLD') ? CRITICAL_THRESHOLD : 500; // ml
                $severity = $pred_qty < ($crit * 0.5) ? 'high' : ($pred_qty < $crit ? 'medium' : 'low');
                if ($severity !== 'low') {
                    $reason = "Forecast predicted low inventory for $bt: $pred_qty ml (< $crit ml)";
                    $create_auto_request($default_hospital_id, $bt, $severity, null, $reason);
                }
            }
        }

        // Use prediction signals
        if (is_array($pred) && !empty($pred['predictions'])) {
            foreach ($pred['predictions'] as $p) {
                if (!isset($p['blood_type'])) continue;
                $bt = $p['blood_type'];
                $status = isset($p['status']) ? strtolower($p['status']) : '';
                $is_shortage = strpos($status, 'shortage') !== false;
                $conf = isset($p['confidence_level']) ? floatval($p['confidence_level'])/100.0 : (isset($p['confidence']) ? floatval($p['confidence']) : 0.0);
                if ($is_shortage && $conf >= 0.7) {
                    $severity = $conf >= 0.85 ? 'high' : 'medium';
                    $reason = "Prediction indicates shortage for $bt (confidence=" . number_format($conf,2) . ")";
                    $create_auto_request($default_hospital_id, $bt, $severity, $conf, $reason);
                }
            }
        }

        // Additionally, scan actual hospital stock and auto-create requests per hospital where critically low
        try {
            $crit = defined('CRITICAL_THRESHOLD') ? CRITICAL_THRESHOLD : 500;
            $low = $conn->query("SELECT h.id AS hospital_id, h.name AS hospital_name, inv.blood_type, COALESCE(inv.quantity_ml,0) AS qty
                                 FROM hospitals h
                                 LEFT JOIN hospital_blood_inventory inv ON inv.hospital_id = h.id
                                 WHERE COALESCE(inv.quantity_ml,0) < $crit");
            if ($low) {
                while ($row = $low->fetch_assoc()) {
                    $hid = (int)$row['hospital_id'];
                    $bt = $row['blood_type'];
                    if (!$bt) continue;
                    $qty_now = (int)$row['qty'];
                    $severity = ($qty_now < $crit * 0.5) ? 'high' : 'medium';
                    $reason = "Actual hospital stock low for $bt: $qty_now ml (< $crit ml)";
                    $req_id = $create_auto_request($hid, $bt, $severity, null, $reason);

                    // If we created a request, also auto-select a blood bank with sufficient stock and log a transfer request
                    if ($req_id) {
                        $need_qty = ($severity === 'high') ? 1350 : 900; // mirrors create_auto_request
                        $bb = $conn->query("SELECT blood_bank_id, SUM(quantity_ml) AS avail
                                             FROM blood_inventory
                                             WHERE blood_type = '" . $conn->real_escape_string($bt) . "' AND status = 'available'
                                             GROUP BY blood_bank_id
                                             HAVING avail >= $need_qty
                                             ORDER BY avail DESC
                                             LIMIT 1")->fetch_assoc();
                        $bank_id = $bb ? (int)$bb['blood_bank_id'] : 0;
                        $tr = $conn->prepare("INSERT INTO blood_transfers (blood_bank_id, hospital_id, blood_type, quantity_ml, request_type, status, transfer_date, notes, proposal_origin, proposed_by, created_at)
                                               VALUES (?, ?, ?, ?, 'automatic', 'requested', NOW(), ?, 'ml_service', 'ml_service', NOW())");
                        if ($tr) {
                            $notes = $reason . ($bank_id ? " | Assigned bank #$bank_id" : " | No bank with sufficient stock found");
                            $tr->bind_param('iisis', $bank_id, $hid, $bt, $need_qty, $notes);
                            $tr->execute();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // ignore failures but keep cron running
        }
    }
}

// Done
echo "AI cron run completed\n";

