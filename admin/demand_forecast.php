<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Demand Forecast";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Blood Demand Forecast</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="refreshBtn">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="exportBtn">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                    <div class="dropdown
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="timeRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="far fa-calendar-alt me-1"></i> Time Range
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="timeRangeDropdown">
                        <li><a class="dropdown-item time-range" href="#" data-range="7">Last 7 Days</a></li>
                        <li><a class="dropdown-item time-range active" href="#" data-range="30">Last 30 Days</a></li>
                        <li><a class="dropdown-item time-range" href="#" data-range="90">Last 90 Days</a></li>
                        <li><a class="dropdown-item time-range" href="#" data-range="180">Last 6 Months</a></li>
                        <li><a class="dropdown-item time-range" href="#" data-range="365">Last Year</a></li>
                    </ul>
                </div>
            </div>

            <!-- Alerts -->
            <div id="alertContainer"></div>

            <!-- Forecast Content -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-line me-1"></i>
                            Blood Demand Forecast
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height:400px;">
                                <canvas id="forecastChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            Forecast Data
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="forecastTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Blood Type</th>
                                            <th>Forecasted Demand</th>
                                            <th>Confidence Interval (95%)</th>
                                            <th>Current Inventory</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="forecastTableBody">
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
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>

<script>
$(document).ready(function() {
    // Initialize chart
    let forecastChart;
    let currentRange = 30; // Default to 30 days
    
    // Load initial data
    loadForecastData(currentRange);
    
    // Event Listeners
    $('#refreshBtn').click(function() {
        loadForecastData(currentRange);
    });
    
    $('.time-range').click(function(e) {
        e.preventDefault();
        currentRange = $(this).data('range');
        $('.time-range').removeClass('active');
        $(this).addClass('active');
        loadForecastData(currentRange);
    });
    
    $('#exportBtn').click(function() {
        exportForecastData();
    });
    
    // Function to load forecast data
    function loadForecastData(days) {
        $.ajax({
            url: 'ajax/get_forecast_data.php',
            type: 'GET',
            data: { days: days },
            dataType: 'json',
            beforeSend: function() {
                // Show loading state
                $('#refreshBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
            },
            success: function(response) {
                if (response.status === 'success') {
                    updateChart(response.data.chartData);
                    updateForecastTable(response.data.forecast);
                    showAlert('Forecast data loaded successfully!', 'success');
                } else {
                    showAlert('Error loading forecast data: ' + response.message, 'danger');
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
        const ctx = document.getElementById('forecastChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (forecastChart) {
            forecastChart.destroy();
        }
        
        // Process datasets for Chart.js
        const datasets = [];
        const bloodTypes = Object.keys(chartData.datasets);
        
        bloodTypes.forEach(type => {
            const color = getBloodTypeColor(type);
            datasets.push({
                label: `${type} - Actual`,
                data: chartData.datasets[type].actual,
                borderColor: color,
                backgroundColor: 'transparent',
                borderWidth: 2,
                pointRadius: 2,
                borderDash: [5, 5]
            });
            
            datasets.push({
                label: `${type} - Forecast`,
                data: chartData.datasets[type].forecast,
                borderColor: color,
                backgroundColor: hexToRgba(color, 0.1),
                borderWidth: 2,
                pointRadius: 3,
                fill: true
            });
            
            // Add confidence interval if available
            if (chartData.datasets[type].confidenceInterval) {
                datasets.push({
                    label: `${type} - 95% Confidence`,
                    data: chartData.datasets[type].confidenceInterval,
                    backgroundColor: hexToRgba(color, 0.1),
                    borderColor: 'transparent',
                    borderWidth: 0,
                    pointRadius: 0,
                    fill: 1
                });
            }
        });
        
        // Create new chart
        forecastChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            tooltipFormat: 'PP'
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Units of Blood'
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
                                if (context.parsed.y !== null) {
                                    label += Math.round(context.parsed.y * 10) / 10;
                                }
                                return label;
                            }
                        }
                    },
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }
    
    // Function to update forecast table
    function updateForecastTable(forecastData) {
        const tbody = $('#forecastTableBody');
        tbody.empty();
        
        if (forecastData.length === 0) {
            tbody.append('<tr><td colspan="6" class="text-center">No forecast data available</td></tr>');
            return;
        }
        
        forecastData.forEach(item => {
            const status = getStatusBadge(item.status);
            const row = `
                <tr>
                    <td>${item.date}</td>
                    <td>${item.blood_type}</td>
                    <td>${item.forecasted_demand}</td>
                    <td>${item.confidence_interval}</td>
                    <td>${item.current_inventory}</td>
                    <td>${status}</td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    // Function to export forecast data
    function exportForecastData() {
        // In a real implementation, this would generate a CSV or Excel file
        showAlert('Export functionality will be implemented soon!', 'info');
    }
    
    // Helper function to get blood type color
    function getBloodTypeColor(type) {
        const colors = {
            'O+': '#FF6384',
            'O-': '#36A2EB',
            'A+': '#FFCE56',
            'A-': '#4BC0C0',
            'B+': '#9966FF',
            'B-': '#FF9F40',
            'AB+': '#C9CBCF',
            'AB-': '#4D4D4D'
        };
        return colors[type] || '#' + Math.floor(Math.random()*16777215).toString(16);
    }
    
    // Helper function to convert hex to rgba
    function hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
    
    // Helper function to get status badge
    function getStatusBadge(status) {
        const statusClass = {
            'Adequate': 'success',
            'Low': 'warning',
            'Critical': 'danger',
            'Excess': 'info'
        }[status] || 'secondary';
        
        return `<span class="badge bg-${statusClass}">${status}</span>`;
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
