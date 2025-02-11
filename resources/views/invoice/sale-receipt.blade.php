<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Receipt</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .container {
        max-width: 48rem;
        margin: 0 auto;
        font-family: Arial, sans-serif;
        background-color: white;
        overflow: hidden;
    }
    .invoice-content {
        padding: 2rem 1.5rem;
    }
    .flex {
        display: flex;
    }
    .justify-between {
        justify-content: space-between;
    }
    .items-start {
        align-items: flex-start;
    }
    .mb-8 {
        margin-bottom: 2rem;
    }
    .text-right {
        text-align: right;
    }
    h1, h2, h3, p {
        margin: 0;
        padding: 0;
    }
    h1 {
        font-size: 1.5rem;
        font-weight: bold;
        color: #1f2937;
    }
    h2 {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
    }
    h3 {
        font-size: 1rem;
        font-weight: 600;
        color: #1f2937;
    }
    p {
        font-size: 0.875rem;
        color: #4b5563;
    }
    .mt-1 {
        margin-top: 0.25rem;
    }
    .mb-2 {
        margin-bottom: 0.5rem;
    }
    .whitespace-pre-line {
        white-space: pre-line;
    }
    .icon {
        color: #6b7280;
        margin-right: 0.5rem;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 2rem;
    }
    th, td {
        padding: 1rem 0;
        text-align: left;
        vertical-align: top;
    }
    th {
        font-weight: 600;
        color: #1f2937;
        border-bottom: 1px solid #e5e7eb;
        font-family: Arial, sans-serif;
    }
    td {
        color: #4b5563;
        border-bottom: 1px solid #f3f4f6;
        font-family: Arial, sans-serif;
    }
    .text-right {
        text-align: right;
    }
    .font-semibold {
        font-weight: 600;
    }
    .text-lg {
        font-size: 1.125rem;
    }
    .border-t {
        border-top: 1px solid #e5e7eb;
    }
    .pt-8 {
        padding-top: 2rem;
    }
    .button {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: white;
        background-color: #3b82f6;
        border: none;
        border-radius: 0.375rem;
        cursor: pointer;
    }
    .button:hover {
        background-color: #2563eb;
    }
    .button i {
        margin-right: 0.5rem;
    }

    /* Print specific styles */
    @media print {
        body {
            color: #000;
            background: #fff;
            font-family: Arial, sans-serif;
        }
        .container {
            width: 100%;
            max-width: 100%;
        }
        .flex, .button {
            display: block;
            width: 100%;
        }
        .text-right {
            text-align: left;
        }
        .justify-between {
            justify-content: flex-start;
        }
        .icon {
            display: none;
        }
        .button {
            display: none;
        }
    }
    @media screen, print {
        .flex {
            display: flex;
            width: 100%;
        }
        .justify-between {
            justify-content: space-between;
        }
        .items-start {
            align-items: flex-start;
        }
        .invoice-header, .bill-to {
            width: 50%; /* Adjust width as needed */
        }
        .invoice-header > div, .bill-to > div {
            flex-basis: 100%; /* Adjust this to control space allocation */
        }
        img {
            max-width: 200px; /* Adjust this value based on your needs */
            height: auto;
            display: block;
        }
    }

    /* Specific print styles */
    @media print {
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        .invoice-header, .bill-to {
            width: 50%; /* Ensure each section uses half of the container */
        }
        .invoice-header > div, .bill-to > div {
            text-align: left; /* Ensures text is aligned left on print */
        }
        .text-right {
            text-align: left; /* Override text alignment for print */
        }
        td{
            font-family: Arial, sans-serif;
        }
        th{
            font-family: Arial, sans-serif;
        }
        img {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
    
</style>

</head>
<body>
    <div class="container">
        <div class="invoice-content">
            <div class="flex justify-between items-start mb-8">
                <div>
                    <h1>Receipt</h1>
                    <p class="mt-1">Receipt Number: {{$data->receipt_no}}</p>
                </div>
                <div class="text-right" style="margin-top:-45px !important;">
                    
                    <h1>{{$data->business_name}}</h1>
                </div>
            </div>

            <div class="flex justify-between mb-8">
                <div>
                    <h3>Bill To:</h3>
                    <p>{{$data->customer_name}}</p>
                    <p class="whitespace-pre-line" style="width:200px;">{{$data->customer_address}}, {{$data->customer_city}}</p>
                </div>
                <div class="text-right">
                    <div class="" style="margin-top:-45px !important;">
                        <i class="fas fa-calendar icon"></i>
                        <span>Date: {{$data->receipt_date}}</span>
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $subtotal = 0;
                    $tax = 0;
                    @endphp
                    @foreach ($data->items as $item )
                    <tr>
                        <td>{{$item['product']['title']}}</td>
                        <td class="text-right">{{$item->quantity}}</td>
                        <td class="text-right">{{number_format($item->unit_price)}} Rs</td>
                        <td class="text-right">{{number_format($item->total)}} Rs</td>

                        @php
                        $subtotal += $item->total;
                        $tax += $item->tax;
                        @endphp
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex justify-between mb-8">
                <div></div>
                <div class="text-right">
                    <div class="flex justify-between mb-2">
                        <span class="font-semibold mr-8">Subtotal:</span>
                        <span>{{number_format($subtotal)}} PKR</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="font-semibold mr-8">Tax:</span>
                        <span>{{number_format($tax)}} PKR</span>
                    </div>
                    <div class="flex justify-between text-lg font-semibold">
                        <span class="mr-8">Total:</span>
                        <span>{{number_format($subtotal + $tax)}} PKR</span>
                    </div>
                </div>
            </div>

            <div class="border-t pt-8 mb-8">
                <h3>Notes:</h3>
                <p>Thank you for your business. Please make payment within 30 days.</p>
            </div>
        </div>
    </div>
</body>
</html>