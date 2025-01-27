<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1;
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-lg position-relative">
            <!-- Watermark -->
            <div class="watermark">
                <img src="{{ $watermarkUrl }}" alt="Watermark">
            </div>

            <div class="card-body p-4 position-relative">
                <!-- Header Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h1 class="h2 fw-bold text-dark">Invoice</h1>
                        <p class="text-muted small">Invoice Number: {{ $invoiceNumber }}</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h2 class="h3 fw-semibold text-dark">{{ $companyName }}</h2>
                        <p class="text-muted small white-space-pre-line">{{ $companyAddress }}</p>
                    </div>
                </div>

                <!-- Client & Date Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h3 class="fw-semibold text-dark mb-2">Bill To:</h3>
                        <p class="text-muted small mb-0">{{ $clientName }}</p>
                        <p class="text-muted small white-space-pre-line">{{ $clientAddress }}</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="mb-2">
                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                            <span class="text-muted small">Date: {{ $date }}</span>
                        </div>
                        <div>
                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                            <span class="text-muted small">Due Date: {{ $dueDate }}</span>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table">
                        <thead>
                            <tr class="border-bottom">
                                <th class="text-dark fw-semibold">Description</th>
                                <th class="text-end text-dark fw-semibold">Qty</th>
                                <th class="text-end text-dark fw-semibold">Unit Price</th>
                                <th class="text-end text-dark fw-semibold">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                            <tr class="border-bottom">
                                <td class="text-muted small">{{ $item['description'] }}</td>
                                <td class="text-muted small text-end">{{ $item['quantity'] }}</td>
                                <td class="text-muted small text-end">${{ number_format($item['unitPrice'], 2) }}</td>
                                <td class="text-muted small text-end">${{ number_format($item['quantity'] * $item['unitPrice'], 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totals Section -->
                <div class="row mb-4">
                    <div class="col-md-6 offset-md-6">
                        <div class="text-end">
                            <div class="d-flex justify-content-end mb-2">
                                <span class="fw-semibold text-dark me-3">Subtotal:</span>
                                <span class="text-muted">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-end mb-2">
                                <span class="fw-semibold text-dark me-3">Tax (10%):</span>
                                <span class="text-muted">${{ number_format($tax, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-end">
                                <span class="h5 fw-bold text-dark me-3 mb-0">Total:</span>
                                <span class="h5 fw-bold text-dark mb-0">${{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                <div class="border-top pt-4 mb-4">
                    <h3 class="fw-semibold text-dark mb-2">Notes:</h3>
                    <p class="text-muted small">Thank you for your business. Please make payment within 30 days.</p>
                </div>

                <!-- Print Button -->
                <div class="text-end">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>
                        Print Invoice
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>