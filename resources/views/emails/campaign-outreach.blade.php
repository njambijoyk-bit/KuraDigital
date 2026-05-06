<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? '' }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f5; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 20px; }
        .content { background-color: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .footer { text-align: center; padding: 16px; font-size: 12px; color: #71717a; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="content">
            {!! $bodyHtml !!}
        </div>
        @if($campaignName)
        <div class="footer">
            Sent by {{ $campaignName }} via KuraDigital
        </div>
        @endif
    </div>
</body>
</html>
