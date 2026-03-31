<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'Invoice' }}</title>
    <style>
        {!! $css ?? '' !!}
    </style>
</head>
<body>
@php
    $documentType = strtolower((string) ($documentType ?? 'purchase-invoice'));
    $isReceipt = in_array($documentType, ['sale-receipt', 'receipt'], true);
    $isPurchaseInvoice = $documentType === 'purchase-invoice';

    $documentTitle = $isReceipt ? 'Receipt' : 'Invoice';
    $documentNoLabel = $isReceipt ? 'Receipt No.' : 'Invoice No.';
    $vendorLabel = $isPurchaseInvoice ? 'Vendor' : 'Customer';

    $documentNo = data_get($invoice ?? [], 'receipt_no')
        ?: data_get($invoice ?? [], 'invoice_no')
        ?: '';
    $documentDate = data_get($invoice ?? [], 'receipt_date')
        ?: data_get($invoice ?? [], 'invoice_date')
        ?: '';
    $orderNo = data_get($invoice ?? [], 'order_no')
        ?: data_get($invoice ?? [], 'so_no')
        ?: data_get($invoice ?? [], 'po_no')
        ?: '';

    $items = data_get($invoice ?? [], 'items', []);
    if (!is_array($items)) {
        $items = [];
    }

    $subTotal = 0.0;
    $computedDiscount = 0.0;
    foreach ($items as $line) {
        $qty = (float) data_get($line, 'quantity', 0);
        $unitPrice = (float) data_get($line, 'unit_price', 0);
        $lineDiscount = (float) data_get($line, 'discount', 0);
        $isPercentDiscount = (bool) data_get($line, 'discount_in_percentage', false);

        $subTotal += ($qty * $unitPrice);
        $computedDiscount += $isPercentDiscount ? (($qty * $unitPrice * $lineDiscount) / 100) : $lineDiscount;
    }

    $totalDiscount = (float) data_get($invoice ?? [], 'total_discount', $computedDiscount);
    $totalAmount = (float) data_get($invoice ?? [], 'total', $subTotal - $totalDiscount);
    $previousBalance = (float) data_get($invoice ?? [], 'previous_balance', 0);
    $thisBill = (float) data_get($invoice ?? [], 'this_bill', $totalAmount);
    $currentBalance = (float) data_get($invoice ?? [], 'current_balance', $previousBalance + $thisBill);

    $rowsToRender = 15;
    $emptyRows = max(0, $rowsToRender - count($items));

    $format = function ($value) {
        return number_format((float) $value, 2);
    };
@endphp

<div class="invoice-page">
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="header-left">
                <h1 class="invoice-title">{{ $documentTitle }}</h1>
                <p class="meta-line"><strong>{{ $documentNoLabel }}</strong> {{ $documentNo }}</p>
                <p class="meta-line"><strong>Mobile No :</strong> {{ data_get($company ?? [], 'mobile', '') }}</p>
            </td>
            <td class="header-right">
                <div class="logo-box">
                    @if(data_get($company ?? [], 'logo'))
                        <img src="{{ data_get($company ?? [], 'logo') }}" alt="Company Logo">
                    @else
                        <span>EP</span>
                    @endif
                </div>
                <div class="company-name">{{ data_get($company ?? [], 'name', 'Eeman Prime') }}</div>
                <div class="company-address">{{ data_get($company ?? [], 'address', 'Shahrah e faisal Karachi') }}</div>
            </td>
        </tr>
    </table>

    <div class="section-divider"></div>

    <table class="info-main-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-col">
                <table class="info-card" cellpadding="0" cellspacing="0">
                    <tr>
                        <td colspan="2" class="card-title">{{ $vendorLabel }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Code</td>
                        <td class="value-cell">{{ data_get($invoice ?? [], 'vendor_code', data_get($invoice ?? [], 'customer_code', '')) }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Name</td>
                        <td class="value-cell">{{ data_get($invoice ?? [], 'vendor_name', data_get($invoice ?? [], 'customer_name', '')) }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Address</td>
                        <td class="value-cell">{{ data_get($invoice ?? [], 'vendor_address', data_get($invoice ?? [], 'customer_address', '')) }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">City</td>
                        <td class="value-cell">{{ data_get($invoice ?? [], 'vendor_city', data_get($invoice ?? [], 'customer_city_name', '')) }}</td>
                    </tr>
                </table>
            </td>
            <td class="info-col">
                <table class="info-card" cellpadding="0" cellspacing="0">
                    <tr>
                        <td colspan="2" class="card-title">{{ $documentTitle }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Date</td>
                        <td class="value-cell">{{ $documentDate }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Order No</td>
                        <td class="value-cell">{{ $orderNo }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="col-id">Item ID</th>
                <th class="col-desc">Description</th>
                <th class="col-unit">Unit</th>
                <th class="col-qty">Quantity</th>
                <th class="col-price">Unit Price</th>
                <th class="col-rate">Discount Rate</th>
                <th class="col-disc">Discount Amount</th>
                <th class="col-amt">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                @php
                    $qty = (float) data_get($item, 'quantity', 0);
                    $unitPrice = (float) data_get($item, 'unit_price', 0);
                    $discount = (float) data_get($item, 'discount', 0);
                    $isPercentDiscount = (bool) data_get($item, 'discount_in_percentage', false);
                    $discountAmount = $isPercentDiscount ? (($qty * $unitPrice * $discount) / 100) : $discount;
                    $amount = (float) data_get($item, 'total', ($qty * $unitPrice) - $discountAmount);
                @endphp
                <tr>
                    <td class="txt-left">{{ data_get($item, 'product.id', data_get($item, 'product_id', '')) }}</td>
                    <td class="txt-left uppercase">{{ data_get($item, 'product.title', data_get($item, 'product_name', '')) }}</td>
                    <td class="txt-center">{{ data_get($item, 'measurement_unit', '') }}</td>
                    <td class="txt-center">{{ rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') }}</td>
                    <td class="txt-right">{{ $format($unitPrice) }}</td>
                    <td class="txt-center">{{ $isPercentDiscount ? $discount . '%' : '-' }}</td>
                    <td class="txt-right">{{ $format($discountAmount) }}</td>
                    <td class="txt-right">{{ $format($amount) }}</td>
                </tr>
            @endforeach

            @for($i = 0; $i < $emptyRows; $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <table class="summary-main-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="summary-left">
                <table class="balance-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="balance-label">Previous Balance:</td>
                        <td class="balance-value">{{ $format($previousBalance) }}</td>
                    </tr>
                    <tr>
                        <td class="balance-label">This Bill:</td>
                        <td class="balance-value">{{ $format($thisBill) }}</td>
                    </tr>
                    <tr>
                        <td class="balance-label">Current Balance:</td>
                        <td class="balance-value">{{ $format($currentBalance) }}</td>
                    </tr>
                </table>
            </td>
            <td class="summary-right">
                <table class="totals-table" cellpadding="0" cellspacing="0">
                    <tr class="total-row">
                        <td>Total</td>
                        <td class="txt-right">{{ $format($subTotal) }}</td>
                    </tr>
                    <tr>
                        <td>Less Discount</td>
                        <td class="txt-right">{{ $format($totalDiscount) }}</td>
                    </tr>
                    <tr class="grand-total-row">
                        <td>Total Amount</td>
                        <td class="txt-right">{{ $format($totalAmount) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="prepared-by">Prepared By: {{ data_get($company ?? [], 'preparedBy', 'Admin') }}</p>
</div>
</body>
</html>
