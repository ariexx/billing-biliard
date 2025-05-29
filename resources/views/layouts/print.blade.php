<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $order->order_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            body {
                font-family: 'Arial', sans-serif;
                font-size: 10px;
                margin: 0;
                padding: 3mm;
                width: 74mm;
            }
        }

        .receipt {
            width: 100%;
            max-width: 74mm;
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
        }

        .store-name {
            font-size: 14px;
            margin: 0;
        }

        .store-address {
            margin: 3px 0;
        }

        .order-info {
            margin-bottom: 8px;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th, .items td {
            text-align: left;
            padding: 2px;
            font-size: 9px;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        /* Column widths */
        .items th:nth-child(1), .items td:nth-child(1) { width: 40%; }
        .items th:nth-child(2), .items td:nth-child(2) { width: 15%; }
        .items th:nth-child(3), .items td:nth-child(3) { width: 20%; }
        .items th:nth-child(4), .items td:nth-child(4) { width: 25%; }

        .footer {
            text-align: center;
            margin-top: 8px;
            font-size: 10px;
        }
    </style>
</head>
<body>
@yield('content')
<script>
    window.onload = function() {
        window.print();
    }
</script>
</body>
</html>
