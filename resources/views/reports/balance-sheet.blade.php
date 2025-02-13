<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eeman - Balance Sheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
        }

        .balance-sheet {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            background: #fff;
        }

        h1 {
            font-size: 24px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
        }

        p {
            text-align: right;
            font-size: 14px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #f1f1f1;
            font-weight: bold;
        }

        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .balance-sheet {
                border: none;
                box-shadow: none;
                max-width: 100%;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="container balance-sheet">
        <h1>{{ $data['business_name'] }} - Balance Sheet</h1>
        <p>As of {{ $data['date'] }}</p>

        <table class="table">
            <thead>
                <tr>
                    <th>Assets</th>
                    <th class="text-end">Amount ($)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Cash</td>
                    <td class="text-end">{{ number_format($data['cash_total']) }}</td>
                </tr>
                <tr>
                    <td>Bank</td>
                    <td class="text-end">{{ number_format($data['bank_total']) }}</td>
                </tr>
                <tr>
                    <td>Accounts Receivable (Customers)</td>
                    <td class="text-end">{{ number_format($data['customer_total']) }}</td>
                </tr>
                <tr>
                    <td>Inventory</td>
                    <td class="text-end">{{ number_format($data['inventory_total']) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total Assets</td>
                    <td class="text-end">{{ number_format($data['total_assets']) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="table mt-4">
            <thead>
                <tr>
                    <th>Liabilities and Equity</th>
                    <th class="text-end">Amount ($)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Liabilities</td>
                    <td class="text-end">0</td>
                </tr>
                <tr>
                    <td>Equity</td>
                    <td class="text-end">4,911,580</td>
                </tr>
                <tr class="total-row">
                    <td>Total Liabilities and Equity</td>
                    <td class="text-end">4,911,580</td>
                </tr>
            </tbody>
        </table>

        <div class="text-center no-print mt-4">
            <button onclick="window.print()" class="btn btn-primary">Print</button>
        </div>
    </div>

    <script src="
