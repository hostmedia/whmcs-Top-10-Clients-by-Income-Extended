<?php

// Developed by Host Media Ltd
// https://hostmedia.uk
// Version 1.0.0

use WHMCS\Carbon;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$reportdata["title"] = "Top Clients by Income Extended";
$reportdata["description"] = "This report shows the clients with the highest net income according to the transactions entered in WHMCS.";

// Get parameters
$range = App::getFromRequest('range');
$compare = (bool) App::getFromRequest('compare');
$limit = (int) App::getFromRequest('limit');

// Set defaults if not provided
if (!$range) {
    $range = ""; // Empty range means All Time
}

if (!$limit || $limit < 10) {
    $limit = 10; // Default to top 10 if not specified or invalid
}

// Update title to reflect the number of clients
$reportdata["title"] = "Top {$limit} Clients by Income";

// Parse the date range
$startDate = null;
$endDate = null;
$isAllTime = empty($range);

if (!$isAllTime) {
    $dateRange = Carbon::parseDateRangeValue($range);
    $startDate = $dateRange['from'];
    $endDate = $dateRange['to'];
}

// Calculate comparison date range if enabled
$compareStartDate = null;
$compareEndDate = null;
if ($compare && !$isAllTime) {
    $diffInDays = $startDate->diffInDays($endDate);
    $compareEndDate = clone $startDate;
    $compareEndDate->subDay();
    $compareStartDate = clone $compareEndDate;
    $compareStartDate->subDays($diffInDays);
}

// Create date range picker and quick links
$reportdata['headertext'] = '';
if (!$print) {
    // Prepare quick links with active state
    $today = Carbon::today()->endOfDay();
    $lastMonth = Carbon::today()->subMonth()->startOfDay();
    
    // Define date ranges for quick links
    $allTimeRange = "";
    $currentMonthRange = Carbon::now()->startOfMonth()->toAdminDateFormat() . ' - ' . Carbon::now()->endOfMonth()->toAdminDateFormat();
    $lastMonthRange = Carbon::now()->subMonth()->startOfMonth()->toAdminDateFormat() . ' - ' . Carbon::now()->subMonth()->endOfMonth()->toAdminDateFormat();
    $currentYearRange = Carbon::now()->startOfYear()->toAdminDateFormat() . ' - ' . Carbon::now()->endOfYear()->toAdminDateFormat();
    $lastYearRange = Carbon::now()->subYear()->startOfYear()->toAdminDateFormat() . ' - ' . Carbon::now()->subYear()->endOfYear()->toAdminDateFormat();
    
    // Create links with active state - preserve comparison setting and limit
    $allTimeLink = "reports.php?report={$report}&range=&compare=" . ($compare ? '1' : '0') . "&limit={$limit}";
    $currentMonthLink = "reports.php?report={$report}&range=" . urlencode($currentMonthRange) . "&compare=" . ($compare ? '1' : '0') . "&limit={$limit}";
    $lastMonthLink = "reports.php?report={$report}&range=" . urlencode($lastMonthRange) . "&compare=" . ($compare ? '1' : '0') . "&limit={$limit}";
    $currentYearLink = "reports.php?report={$report}&range=" . urlencode($currentYearRange) . "&compare=" . ($compare ? '1' : '0') . "&limit={$limit}";
    $lastYearLink = "reports.php?report={$report}&range=" . urlencode($lastYearRange) . "&compare=" . ($compare ? '1' : '0') . "&limit={$limit}";
    
    // Determine which button should be active
    $allTimeActive = $isAllTime ? ' btn-primary active' : ' btn-default';
    $currentMonthActive = (!$isAllTime && $range == $currentMonthRange) ? ' btn-primary active' : ' btn-default';
    $lastMonthActive = (!$isAllTime && $range == $lastMonthRange) ? ' btn-primary active' : ' btn-default';
    $currentYearActive = (!$isAllTime && $range == $currentYearRange) ? ' btn-primary active' : ' btn-default';
    $lastYearActive = (!$isAllTime && $range == $lastYearRange) ? ' btn-primary active' : ' btn-default';
    
    // Prepare limit dropdown options
    $limitOptions = [10, 20, 30, 40, 50, 100, 200];
    $limitDropdownOptions = '';
    foreach ($limitOptions as $option) {
        $selected = ($limit == $option) ? ' selected' : '';
        $limitDropdownOptions .= "<option value=\"{$option}\"{$selected}>Top {$option}</option>";
    }
    
    $compareChecked = $compare ? ' checked' : '';
    $compareDisabled = $isAllTime ? ' disabled="disabled"' : '';
    $compareTooltip = $isAllTime ? ' data-toggle="tooltip" title="Comparison is not available for All Time view"' : '';
    
    // Add JavaScript for auto-submit on comparison checkbox change and limit dropdown change
    $autoSubmitScript = <<<JS
<script type="text/javascript">
    $(document).ready(function() {
        // Auto-submit form when comparison checkbox changes
        $('#compareCheckbox').change(function() {
            $(this).closest('form').submit();
        });
        
        // Auto-submit form when limit dropdown changes
        $('#limitDropdown').change(function() {
            $(this).closest('form').submit();
        });
        
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Store settings in localStorage when form submits
        $('form').submit(function() {
            localStorage.setItem('whmcs_report_compare', $('#compareCheckbox').is(':checked') ? '1' : '0');
            localStorage.setItem('whmcs_report_limit', $('#limitDropdown').val());
        });
        
        // When date picker changes, maintain the settings
        $('.date-picker-search').on('apply.daterangepicker', function(ev, picker) {
            // This ensures the settings are maintained when a custom date range is applied
            var compareState = localStorage.getItem('whmcs_report_compare');
            var limitValue = localStorage.getItem('whmcs_report_limit');
            
            if (compareState) {
                $('#compareCheckbox').prop('checked', compareState === '1');
            }
            
            if (limitValue) {
                $('#limitDropdown').val(limitValue);
            }
        });
    });
</script>
JS;
    
    $reportdata['headertext'] = <<<HTML
<form method="post" action="reports.php?report={$report}">
    <div class="report-filters-wrapper">
        <div class="inner-container">
            <h3>Filters</h3>
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="inputFilterDate">Date Range</label>
                        <div class="form-group date-picker-prepend-icon">
                            <label for="inputFilterDate" class="field-icon">
                                <i class="fal fa-calendar-alt"></i>
                            </label>
                            <input id="inputFilterDate"
                                   type="text"
                                   name="range"
                                   value="{$range}"
                                   class="form-control date-picker-search"
                            />
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label>Quick Date Ranges</label>
                        <div class="btn-group btn-group-sm">
                            <a href="{$allTimeLink}" class="btn{$allTimeActive}">All Time</a>
                            <a href="{$currentMonthLink}" class="btn{$currentMonthActive}">Current Month</a>
                            <a href="{$lastMonthLink}" class="btn{$lastMonthActive}">Last Month</a>
                            <a href="{$currentYearLink}" class="btn{$currentYearActive}">Current Year</a>
                            <a href="{$lastYearLink}" class="btn{$lastYearActive}">Last Year</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="limitDropdown">Number of Results</label>
                        <select id="limitDropdown" name="limit" class="form-control">
                            {$limitDropdownOptions}
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label>Comparison</label>
                        <div class="checkbox">
                            <label{$compareTooltip}>
                                <input type="checkbox" id="compareCheckbox" name="compare" value="1"{$compareChecked}{$compareDisabled}>
                                Compare with previous period
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                Generate Report
            </button>
        </div>
    </div>
</form>
{$autoSubmitScript}
HTML;
}

// Set up table headings
if ($compare && !$isAllTime) {
    $reportdata["tableheadings"] = array(
        "Client ID",
        "Client Name",
        "Total Amount In",
        "Total Amount In (Previous)",
        "% Change",
        "Total Fees",
        "Total Amount Out",
        "Balance",
        "Balance (Previous)",
        "% Change"
    );
} else {
    $reportdata["tableheadings"] = array(
        "Client ID",
        "Client Name",
        "Total Amount In",
        "Total Fees",
        "Total Amount Out",
        "Balance"
    );
}

// Function to get client data for a specific date range
function getClientData($startDate, $endDate, $limit = 10) {
    $query = Capsule::table('tblaccounts')
        ->select(
            'tblclients.id',
            'tblclients.firstname',
            'tblclients.lastname',
            Capsule::raw('SUM(tblaccounts.amountin/tblaccounts.rate) AS amountIn'),
            Capsule::raw('SUM(tblaccounts.fees/tblaccounts.rate) AS fees'),
            Capsule::raw('SUM(tblaccounts.amountout/tblaccounts.rate) AS amountOut'),
            Capsule::raw('SUM((tblaccounts.amountin/tblaccounts.rate)-(tblaccounts.fees/tblaccounts.rate)-(tblaccounts.amountout/tblaccounts.rate)) AS balance'),
            'tblaccounts.rate'
        )
        ->join('tblclients', 'tblclients.id', '=', 'tblaccounts.userid');
    
    // Apply date range filter if provided
    if ($startDate && $endDate) {
        $query->whereBetween('tblaccounts.date', [
            $startDate->toDateTimeString(),
            $endDate->toDateTimeString()
        ]);
    }
    
    return $query->groupBy('userid')
        ->orderBy('balance', 'desc')
        ->take($limit)
        ->get()
        ->all();
}

// Get client data for the selected date range
$results = getClientData($startDate, $endDate, $limit);

// Get comparison data if enabled
$compareResults = [];
if ($compare && !$isAllTime && $compareStartDate && $compareEndDate) {
    $compareResults = getClientData($compareStartDate, $compareEndDate, $limit);
    // Convert to associative array by client ID for easier lookup
    $compareResultsById = [];
    foreach ($compareResults as $result) {
        $compareResultsById[$result->id] = $result;
    }
}

// Prepare chart data
$chartdata = [];
$chartdata['cols'] = [];
$chartdata['cols'][] = array('label' => 'Client', 'type' => 'string');
$chartdata['cols'][] = array('label' => 'Balance', 'type' => 'number');
if ($compare && !$isAllTime) {
    $chartdata['cols'][] = array('label' => 'Previous Balance', 'type' => 'number');
}

// Process results
foreach ($results as $result) {
    $userid = $result->id;
    $currency = getCurrency();
    $rate = ($result->rate == "1.00000") ? '' : '*';
    $clientlink = '<a href="clientssummary.php?userid=' . $result->id . '">';
    
    if ($compare && !$isAllTime) {
        // Calculate percentage changes
        $previousAmountIn = isset($compareResultsById[$result->id]) ? $compareResultsById[$result->id]->amountIn : 0;
        $previousBalance = isset($compareResultsById[$result->id]) ? $compareResultsById[$result->id]->balance : 0;
        
        $amountInChange = $previousAmountIn > 0 ? (($result->amountIn - $previousAmountIn) / $previousAmountIn * 100) : 100;
        $balanceChange = $previousBalance > 0 ? (($result->balance - $previousBalance) / $previousBalance * 100) : 100;
        
        $amountInChangeFormatted = round($amountInChange, 2) . '%';
        $balanceChangeFormatted = round($balanceChange, 2) . '%';
        
        // Add color coding for percentage changes
        $amountInChangeClass = $amountInChange > 0 ? 'text-success' : ($amountInChange < 0 ? 'text-danger' : '');
        $balanceChangeClass = $balanceChange > 0 ? 'text-success' : ($balanceChange < 0 ? 'text-danger' : '');
        
        $amountInChangeFormatted = '<span class="' . $amountInChangeClass . '">' . $amountInChangeFormatted . '</span>';
        $balanceChangeFormatted = '<span class="' . $balanceChangeClass . '">' . $balanceChangeFormatted . '</span>';
        
        $reportdata["tablevalues"][] = [
            $clientlink . $result->id . '</a>',
            $clientlink . $result->firstname . ' ' . $result->lastname . '</a>',
            formatCurrency($result->amountIn) . " $rate",
            formatCurrency($previousAmountIn) . " $rate",
            $amountInChangeFormatted,
            formatCurrency($result->fees) . " $rate",
            formatCurrency($result->amountOut) . " $rate",
            formatCurrency($result->balance) . " $rate",
            formatCurrency($previousBalance) . " $rate",
            $balanceChangeFormatted
        ];
        
        // Add to chart data
        $chartdata['rows'][] = [
            'c' => [
                [
                    'v' => $result->firstname . ' ' . $result->lastname,
                ],
                [
                    'v' => round($result->balance, 2),
                    'f' => formatCurrency($result->balance),
                ],
                [
                    'v' => round($previousBalance, 2),
                    'f' => formatCurrency($previousBalance),
                ]
            ]
        ];
    } else {
        $reportdata["tablevalues"][] = [
            $clientlink . $result->id . '</a>',
            $clientlink . $result->firstname . ' ' . $result->lastname . '</a>',
            formatCurrency($result->amountIn) . " $rate",
            formatCurrency($result->fees) . " $rate",
            formatCurrency($result->amountOut) . " $rate",
            formatCurrency($result->balance) . " $rate",
        ];
        
        // Add to chart data
        $chartdata['rows'][] = [
            'c' => [
                [
                    'v' => $result->firstname . ' ' . $result->lastname,
                ],
                [
                    'v' => round($result->balance, 2),
                    'f' => formatCurrency($result->balance),
                ]
            ]
        ];
    }
}

$reportdata["footertext"] = "<p>* denotes converted to default currency</p>";

// Add date range information to footer
if ($startDate && $endDate) {
    $reportdata["footertext"] .= "<p>Report period: " . $startDate->toAdminDateFormat() . " to " . $endDate->toAdminDateFormat() . "</p>";
    
    if ($compare && $compareStartDate && $compareEndDate) {
        $reportdata["footertext"] .= "<p>Comparison period: " . $compareStartDate->toAdminDateFormat() . " to " . $compareEndDate->toAdminDateFormat() . "</p>";
    }
} else {
    $reportdata["footertext"] .= "<p>Report period: All Time</p>";
}

// Draw chart
$args = array();
$args['legendpos'] = 'right';

$reportdata["headertext"] .= $chart->drawChart('Pie', $chartdata, $args, '300px');
