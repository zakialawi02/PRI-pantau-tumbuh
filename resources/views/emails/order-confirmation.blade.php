<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Order Confirmation</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.2;
                color: #333;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }

            .container {
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }

            .header {
                background: #219d53;
                background: -webkit-linear-gradient(90deg,
                        rgba(33, 157, 83, 1) 0%,
                        rgba(95, 176, 187, 1) 100%);
                background: -moz-linear-gradient(90deg,
                        rgba(33, 157, 83, 1) 0%,
                        rgba(95, 176, 187, 1) 100%);
                background: linear-gradient(90deg,
                        rgba(33, 157, 83, 1) 0%,
                        rgba(95, 176, 187, 1) 100%);
                filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#219D53", endColorstr="#5FB0BB", GradientType=1);
                color: white;
                padding: 20px;
                text-align: center;
            }

            .content {
                padding: 30px;
            }

            .footer {
                background-color: #f8f9fa;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #6c757d;
            }

            .button {
                display: inline-block;
                background-color: #219d53;
                color: white;
                padding: 8px 16px;
                text-decoration: none;
                border-radius: 4px;
                margin: 20px 0;
            }

            .order-details {
                background-color: #f8f9fa;
                border-radius: 4px;
                padding: 15px;
                margin: 20px 0;
            }

            .order-row {
                padding: 8px 0;
                border-bottom: 1px solid #e9ecef;
            }

            .order-total {
                font-weight: bold;
                font-size: 18px;
                color: #2563eb;
            }

            .status-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: bold;
            }

            .status-pending {
                background-color: #fff3cd;
                color: #856404;
            }

            .status-paid {
                background-color: #d4edda;
                color: #155724;
            }

            /* Table styles for email compatibility */
            .email-table {
                width: 100%;
                border-collapse: collapse;
            }

            .email-table td {
                padding: 8px 0;
            }

            .email-table .label {
                font-weight: bold;
            }

            .email-table .value {
                text-align: right;
            }

            .summary-table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
            }

            .summary-table td {
                padding: 5px 0;
            }

            .text-right {
                text-align: right;
            }

            .text-center {
                text-align: center;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="header">
                <h1>Order Confirmation - Payment Pending</h1>
                <p>Complete your payment to activate your order!</p>
                <span>{{ Config::get('app.name', 'PantauTumbuh.id') }}</span>
            </div>

            <div class="content">
                <h2>Hello {{ $payment->name }},</h2>

                <p>
                    Thank you for your order. Please complete your payment to activate your subscription and access all features.
                </p>

                <div class="order-details">
                    <table class="email-table">
                        <tr class="order-row">
                            <td class="label">Order Number:</td>
                            <td class="value">#{{ substr($payment->id, 0, 8) }}</td>
                        </tr>
                        <tr class="order-row">
                            <td class="label">Order Date:</td>
                            <td class="value">{{ $payment->created_at->format('F j, Y H:i') }}</td>
                        </tr>
                        <tr class="order-row">
                            <td class="label">Payment Method:</td>
                            <td class="value">
                                @if ($payment->payment_method === 'bank_transfer')
                                    Bank Transfer
                                @elseif($payment->payment_method === 'paypal')
                                    PayPal
                                @else
                                    {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                @endif
                            </td>
                        </tr>
                        <tr class="order-row">
                            <td class="label">Status:</td>
                            <td class="value">
                                <span class="status-badge status-{{ $payment->status }}">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span>
                            </td>
                        </tr>
                        @if ($payment->due_date)
                            <tr class="order-row">
                                <td class="label">Payment Due:</td>
                                <td class="value">{{ $payment->due_date->format('F j, Y H:i') }}</td>
                            </tr>
                        @endif
                    </table>
                </div>

                <h3>Order Summary</h3>
                <div class="order-details">
                    <table class="summary-table">
                        <tr class="order-row">
                            <td>
                                <span>Plan: {{ $payment->subscription->plan->name }}</span>
                                <br>
                                <span style="color: #858585; font-size: 14px;">Field: {{ $payment->subscription->fieldArea->name }}</span>
                            </td>
                            <td class="text-right">{{ Number::format($payment->subscription->fieldArea->area_ha, locale: app()->getLocale()) }} ha</td>
                        </tr>
                        <tr class="order-row">
                            <td>Rate per Hectare</td>
                            <td class="text-right">{{ Number::currency($payment->subscription->price_per_hectare, $payment->currency, app()->getLocale()) }}</td>
                        </tr>
                        <tr class="order-row">
                            <td><strong>Total</strong></td>
                            <td class="text-right"><strong class="order-total">{{ Number::currency($payment->amount, $payment->currency, app()->getLocale()) }}</strong></td>
                        </tr>
                    </table>
                </div>

                <p class="text-center">
                    <a class="button" href="{{ route('admin.payment.show', $payment->id) }}">View Order Details</a>
                </p>

                <p>
                    If you have any questions about your order, please don't
                    hesitate to contact our support team.
                </p>
            </div>

            <div class="footer">
                <p>© {{ date('Y') }} PantauTumbuh.id. All rights reserved.</p>
                <p>This is an automated email, please do not reply.</p>
            </div>
        </div>
    </body>

</html>
