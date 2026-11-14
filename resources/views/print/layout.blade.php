<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { width: 72mm; margin: 0 auto; padding: 4mm 0; font: 12px/1.35 "Courier New", ui-monospace, monospace; color: #000; background: #fff; }
        h1 { font-size: 15px; margin: 0; text-align: center; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; } .big { font-size: 16px; }
        .muted { font-size: 11px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .tag { border: 1px solid #000; padding: 1px 6px; display: inline-block; font-weight: bold; }
        .noprint { margin-top: 12px; text-align: center; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
@yield('body')
<div class="noprint"><button type="button" id="print-btn">Print</button> <button type="button" id="close-btn">Close</button></div>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    document.getElementById('print-btn').addEventListener('click', () => window.print());
    document.getElementById('close-btn').addEventListener('click', () => window.close());
    window.addEventListener('load', () => setTimeout(() => window.print(), 150));
</script>
</body>
</html>
