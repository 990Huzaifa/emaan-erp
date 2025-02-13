<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eeman - Balance Sheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .balance-sheet {
            max-width: 800px;
            margin: 0 auto;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container mt-5 balance-sheet">
        <h1 class="text-center mb-4">{{ $data['business_name'] }} - Balance Sheet</h1>
        <p class="text-end mb-4">As of {{ $data['date'] }}</p>
        
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th scope="col">Assets</th>
                    <th scope="col" class="text-end">Amount</th>
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
        
        <table class="table table-bordered mt-4">
            <thead class="table-light">
                <tr>
                    <th scope="col">Liabilities and Equity</th>
                    <th scope="col" class="text-end">Amount ($)</th>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

