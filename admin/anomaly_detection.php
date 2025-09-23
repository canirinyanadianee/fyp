<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Anomaly Detection";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Anomaly Detection</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="refreshBtn">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <div id="alertContainer"></div>

            <!-- Anomaly Detection Content -->
            <div class="row">
                <div class="col-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-chart-line me-1"></i>
                            Blood Donation Anomalies
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height:400px;">
                                <canvas id="anomalyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Detected Anomalies
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="anomaliesTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Blood Type</th>
                                            <th>Expected</th>
                                            <th>Actual</th>
                                            <th>Deviation</th>
                                            <th>Severity</th>
                                        </tr>
                                    </thead>
                                    <tbody id="anomaliesTableBody">
                                        <!-- Data will be loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    // Initialize chart
    let anomalyChart;
    
    // Load initial data
    loadAnomalyData();
    
    // Refresh button click handler
    $('#refreshBtn').click(function() {
        loadAnomalyData();
    });
    
    // Function to load anomaly data
    function loadAnomalyData() {
        $.ajax({
            url: 'ajax/get_anomaly_data.php',
            type: 'GET',
            dataType: 'json',
            beforeSend: function() {
                // Show loading state
                $('#refreshBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
            },
            success: function(response) {
                if (response.status === 'success') {
                    updateChart(response.data.chartData);
                    updateAnomaliesTable(response.data.anomalies);
                    showAlert('Data refreshed successfully!', 'success');
                } else {
                    showAlert('Error loading anomaly data: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Error: ' + error, 'danger');
            },
            complete: function() {
                // Reset button state
                $('#refreshBtn').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Refresh');
            }
        });
    }
    
    // Function to update the chart
    function updateChart(chartData) {
        const ctx = document.getElementById('anomalyChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (anomalyChart) {
            anomalyChart.destroy();
        }
        
        // Create new chart
        anomalyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Expected Donations',
                        data: chartData.expected,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Actual Donations',
                        data: chartData.actual,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: function(context) {
                            return context.raw.isAnomaly ? 'rgba(255, 99, 132, 1)' : 'rgba(75, 192, 192, 1)';
                        },
                        pointRadius: function(context) {
                            return context.raw.isAnomaly ? 6 : 3;
                        },
                        pointHoverRadius: function(context) {
                            return context.raw.isAnomaly ? 8 : 5;
                        }
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Donations'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.raw.y;
                                if (context.raw.isAnomaly) {
                                    label += ' (Anomaly)';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Function to update anomalies table
    function updateAnomaliesTable(anomalies) {
        const tbody = $('#anomaliesTableBody');
        tbody.empty();
        
        if (anomalies.length === 0) {
            tbody.append('<tr><td colspan="6" class="text-center">No anomalies detected</td></tr>');
            return;
        }
        
        anomalies.forEach(anomaly => {
            const row = `
                <tr>
                    <td>${anomaly.date}</td>
                    <td>${anomaly.blood_type}</td>
                    <td>${anomaly.expected}</td>
                    <td>${anomaly.actual}</td>
                    <td>${anomaly.deviation}%</td>
                    <td><span class="badge bg-${getSeverityClass(anomaly.severity)}">${anomaly.severity}</span></td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    // Helper function to get severity class
    function getSeverityClass(severity) {
        switch(severity.toLowerCase()) {
            case 'high': return 'danger';
            case 'medium': return 'warning';
            case 'low': return 'info';
            default: return 'secondary';
        }
    }
    
    // Helper function to show alerts
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#alertContainer').html(alertHtml);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
