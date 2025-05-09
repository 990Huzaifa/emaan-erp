<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eman Traders - Invoice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", sans-serif;
        }

        body {
            background-color: #f5f5f5;
            padding: 0px;
            color: #333;
        }

        hr {
            border: none;
            border-top: 1px solid #ddd;
            margin: 10px 0;
            width: 100%;
        }

        .invoice-container {
            max-width: 1024px;
            margin: 0 auto;
            background-color: white
        }

        /* Header styles */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0px 20px;
        }

        .company-logo {
            display: flex;
            align-items: flex-end;
        }

        .logo-container {
            margin-right: 10px;
        }

        .logo-container img {
            width: 150px;
            height: auto;
            border-radius: 5px;
        }

        .company-info {
            display: flex;
            flex-direction: column;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .company-name .e {
            color: #1e88e5;
        }

        .company-details {
            font-size: 10px;
            line-height: 1.5;
            color: #555;
        }

        .company-address {
            display: flex;
            align-items: flex-end;
            text-align: right;
            font-size: 10px;
            line-height: 1.5;
            color: #555;
        }

        .invoice-body {
            padding: 20px;
            border: 2.08px solid #D7DAE0;
            border-radius: 20px;
        }

        /* Invoice info styles */
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .info-column {
            display: flex;
            flex-direction: column;
            width: 23%;
        }

        .info-title {
            font-weight: normal;
            color: #555;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 12px;
        }

        .invoice-amount {
            font-size: 16px;
            font-weight: bold;
            color: #1e88e5;
        }

        /* Table styles */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .invoice-table th {
            padding: 8px;
            text-align: left;
            font-size: 10px;
            border-top: 1px solid #ddd;
            border-bottom: none;
            color: #555;
            font-weight: normal;
            background-color: transparent;
        }

        .invoice-table td {
            padding: 8px;
            border: none;
            font-size: 12px;
        }

        .invoice-table .text-right {
            text-align: right;
        }

        .invoice-table .text-center {
            text-align: center;
        }

        .invoice-table tr.total-row td {
            border-top: 1px solid #ddd;
        }

        /* Summary styles */
        .invoice-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 10px 0px;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }

        .amount-words {
            width: 60%;
            font-size: 12px;
            padding: 0;
        }

        .amount-calculations {
            width: 35%;
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 5px 0;
        }

        .calc-row.total {
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Footer styles */
        .invoice-footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-section {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .signature-text {
            margin-bottom: 5px;
            font-weight: bold;
        }

        .signature-line {
            width: 250px;
            border-top: 1px solid #777;
        }

        .terms-container {
            margin-top: 20px;
            color: #555;
        }

        .terms-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        .terms-text {
            font-size: 12px;
        }

        /* Print styles */
        @media print {
            body {
                background-color: white;
                padding: 0;
                font-size: 10px;
            }

            .invoice-container {
                box-shadow: none;
                padding: 15px;
            }

            .invoice-header {
                padding: 0px;
                margin-bottom: 20px;
            }

            .company-logo img {
                width: 120px;
            }

            .company-info .company-name {
                font-size: 16px;
            }

            .invoice-table th {
                font-size: 10px;
                padding: 5px;
            }

            .invoice-table td {
                font-size: 10px;
                padding: 5px;
            }

            .signature-text {
                font-size: 10px;
            }

            .terms-title {
                font-size: 10px;
            }

            .terms-text {
                font-size: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="company-logo">
            <div class="logo-container">
                    <img src="{{ url($data->business_logo) }}" alt="Logo">
                </div>
                <div class="company-info">
                    <div class="company-details">
                    <p>{{ url('/') }}</p><br>
                        {{ $data->business_telephone }}
                    </div>
                </div>
            </div>
            <div class="company-address">
                {{ $data->business_address }}, {{ $data->business_city_name }}
            </div>
        </div>

        <div class="invoice-body">
            <!-- Invoice Info -->
            <div class="invoice-info">
                <div class="info-column">
                    <div class="info-title">Billed to</div>
                    <div class="info-value">M/s {{$data->customer_name}} E/S</div>
                    <div class="info-value"><strong>Address:</strong> {{$data->customer_address}}, {{$data->customer_city_name}}</div>
                    <div class="info-value"><strong>Telephone:</strong> {{$data->customer_phone}}</div>
                </div>

                <div class="info-column">
                    <div class="info-title">Invoice no.</div>
                    <div class="info-value">#{{$data->receipt_no}}</div>
                </div>

                <div class="info-column">
                    <div class="info-title">Invoice date</div>
                    <div class="info-value">{{$data->receipt_date}}</div>
                </div>

                <div class="info-column">
                    <div class="info-title">Invoice of</div>
                    <div class="invoice-amount">Rs {{number_format($data->total)}}</div>
                </div>
            </div>

            <!-- Invoice Table -->
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>S NO.</th>
                        <th>ITEM DETAIL</th>
                        <th class="text-center">QTY</th>
                        <th class="text-right">RATE</th>
                        <th class="text-right">GROSS AMOUNT</th>
                        <th class="text-right">TAX</th>
                        <th class="text-right">DISCOUNT</th>
                        <th class="text-right">NET AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $subtotal = 0;
                    $tax = 0;
                    @endphp
                    @foreach ($data->items as $item )
                    <tr>
                        <td>{{$loop->iteration}}</td>
                        <td>{{$item['product']['title']}}</td>
                        <td class="text-right">{{$item->quantity}} {{$item->measurement_unit}}</td>
                        <td class="text-right">{{number_format($item->unit_price)}} Rs</td>
                        <td class="text-right">{{number_format($item->discount)}} @if($item->discount_in_percentage == 1)% @else Rs @endif</td>
                        <td class="text-right">{{number_format($item->total - $item->tax)}} Rs</td>
                        <td class="text-right">{{number_format($item->tax)}} Rs</td>
                        <td class="text-right">{{number_format($item->total)}} Rs</td>

                        
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Invoice Summary -->
            <div class="invoice-summary">
                <div class="amount-words">
                    <strong>Amount in Words:</strong><br>
                    {{ convertNumberToWords($item->total) }} only
                    
                </div>
                <div class="amount-calculations">
                    <div class="calc-row">
                        <div>Subtotal</div>
                        <div>Rs. {{number_format($item->total)}}</div>
                    </div>
                    <div class="calc-row">
                        <div>Amount Received</div>
                        <div>Rs. {{number_format($item->total)}}</div>
                    </div>
                    <hr>
                    <div class="calc-row total">
                        <div>Balanced</div>
                        <div>Rs. {{ number_format($current_balance) }}</div>
                    </div>
                </div>
            </div>

            <!-- Invoice Footer -->
            <div class="invoice-footer">
                <div class="signature-section">
                    <div class="signature-text">Authorized Signatures</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature-section">
                    <div class="signature-text">Prepared by Administrated</div>
                </div>
            </div>
        </div>

        <div class="terms-container">
            <div class="terms-title">Terms & Conditions</div>
            <div class="terms-text">Please pay within 15 days of receiving this invoice.</div>
        </div>
    </div>
</body>

</html>