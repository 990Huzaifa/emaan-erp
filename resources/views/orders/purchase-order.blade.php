<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
        }

        .purchase-order {
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

        .po-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        .po-header-left, .po-header-right {
            width: 48%;
        }

        .po-header h2 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }

        .po-details {
            margin-bottom: 10px;
        }

        .po-details div {
            margin-bottom: 5px;
        }

        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .subtotal-section {
            width: 40%;
            margin-left: auto;
        }

        .subtotal-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }

        .subtotal-label {
            font-weight: bold;
        }

        .grand-total {
            font-weight: bold;
            font-size: 16px;
            border-top: 1px solid #ddd;
            padding-top: 5px;
            margin-top: 5px;
        }

        .notes {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .purchase-order {
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
    <div class="purchase-order">
        <h1>PURCHASE ORDER</h1>
        
        <div class="po-header">
            <div class="po-header-left">
                <h2>Vendor Information</h2>
                <div class="po-details">
                    <div><span class="label">Vendor:</span> {{ $data->vendor_name }}.</div>
                    <div><span class="label">Address:</span> {{ $data->vendor_address }}, Supplier City</div>
                    <div><span class="label">Phone:</span> {{ $data->vendor_telephone }}</div>
                    <div><span class="label">Email:</span> {{ $data->vendor_email }}</div>
                </div>
            </div>
            
            <div class="po-header-right">
                <h2>Purchase Order Details</h2>
                <div class="po-details">
                    <div><span class="label">PO Number:</span> {{ $data->order_code }}</div>
                    <div><span class="label">Date:</span> {{ $data->order_date }}</div>
                    <div><span class="label">Payment Terms:</span> {{ $data->terms_of_payment }}</div>
                    <div><span class="label">Delivery Date:</span> {{ $data->due_date }}</div>
                </div>
            </div>
        </div>
        
        
        <table>
            <thead>
                <tr>
                    <th>Item #</th>
                    <th>Product</th>
                    <th>unit</th>
                    < class="text-center">QTY</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Discount</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data->items as $item)
                    
                @endforeach
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item['product']['title'] }}</td>
                    <td>{{ $item->measurement_unit }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-end">{{ number_format($item->unit_price) }}</td>
                    <td class="text-right">{{number_format($item->tax)}} Rs</td>
                        <td class="text-right">{{number_format($item->discount)}} @if($item->discount_in_percentage == 1)% @else Rs @endif</td>
                    <td class="text-end">{{ number_format($item->total) }}</td>
                </tr>
            </tbody>
        </table>
        
        <div class="subtotal-section">
            <div class="subtotal-row">
                <div class="subtotal-label">Subtotal:</div>
                <div>{{ number_format($data->total - $data->total_tax - $data->delivery_cost) }}</div>
            </div>
            <div class="subtotal-row">
                <div class="subtotal-label">Tax:</div>
                <div>{{ number_format($data->total_tax) }}</div>
            </div>
            <div class="subtotal-row">
                <div class="subtotal-label">Shipping:</div>
                <div>{{ number_format($data->delivery_cost) }}</div>
            </div>
            <div class="subtotal-row grand-total">
                <div class="subtotal-label">GRAND TOTAL:</div>
                <div>{{ number_format($data->total) }}</div>
            </div>
        </div>
        
        <div class="notes">
            <h3>Notes & Terms</h3>
            <p>1. Please send two copies of your invoice.</p>
            <p>2. Enter this order in accordance with the prices, terms, delivery method, and specifications listed above.</p>
            <p>3. Please notify us immediately if you are unable to ship as specified.</p>
            <p>4. Send all correspondence to: eemantraders.com</p>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">Authorized by</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Accepted by</div>
            </div>
        </div>
    </div>
</body>
</html>