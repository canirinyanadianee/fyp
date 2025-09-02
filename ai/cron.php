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
$fc = call_api($base . '/api/forecast-inventory?days=7');
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
			// insert notification
			$stmt = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, priority, created_at) VALUES ('bloodbank', 0, ?, ?, 'high', NOW())");
			if ($stmt) {
				$stmt->bind_param('ss', $msg, $p['blood_type']);
				$stmt->execute();
			}

				// propose a transfer by inserting into blood_transfers as 'automatic' and status 'proposed'
				// We don't know which blood_bank to pull from, so create a generic proposal with blood_bank_id=0 and hospital_id=0 (admin to assign)
				$qty = 450; // default unit — you can adjust logic to compute quantity based on predicted shortfall
				$tr = $conn->prepare("INSERT INTO blood_transfers (blood_bank_id, hospital_id, blood_type, quantity_ml, request_type, status, transfer_date, notes, proposal_origin, proposed_by, created_at) VALUES (0, 0, ?, ?, 'automatic', 'proposed', CURDATE(), ?, 'ml_service', 'ml_service', NOW())");
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
				$stmt = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, priority, created_at) VALUES ('hospital', 0, ?, ?, 'high', NOW())");
				if ($stmt) {
					$stmt->bind_param('ss', $msg, $bt);
					$stmt->execute();
				}

				// propose transfer row (admin must assign blood_bank_id & hospital_id if needed)
				$qty = 450;
				$tr = $conn->prepare("INSERT INTO blood_transfers (blood_bank_id, hospital_id, blood_type, quantity_ml, request_type, status, transfer_date, notes, proposal_origin, proposed_by, created_at) VALUES (0, 0, ?, ?, 'automatic', 'proposed', CURDATE(), ?, 'ml_service', 'ml_service', NOW())");
				if ($tr) {
					$notes = $msg;
					$tr->bind_param('sis', $bt, $qty, $notes);
					$tr->execute();
				}
			}
		}
	}
}

// Done
echo "AI cron run completed\n";

