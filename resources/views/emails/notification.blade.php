<!DOCTYPE html>
<html>
<head>
    <title>PropertyHub Ghana</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F8F9FC; color: #1A1D20; margin: 0; padding: 40px; }
        .card { background-color: #FFFFFF; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #E4E8EE; overflow: hidden; max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #F59E0B, #D97706); padding: 30px; text-align: center; color: #FFFFFF; font-size: 24px; font-weight: bold; }
        .content { padding: 40px; line-height: 1.6; font-size: 15px; }
        .footer { background-color: #F1F3F9; padding: 20px; text-align: center; font-size: 11px; color: #8F9BB3; border-top: 1px solid #E4E8EE; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #F59E0B; color: #1A1D20 !important; font-weight: bold; border-radius: 8px; text-decoration: none; margin-top: 20px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">PropertyHub Ghana</div>
        <div class="content">
            <h2 style="margin-top: 0; color: #0F172A;">{{ $title }}</h2>
            <p>{{ $messageText }}</p>
            <p style="color: #64748B; font-size: 12px; margin-top: 20px;">Category: <strong>{{ strtoupper(str_replace('_', ' ', $type)) }}</strong></p>
            <a href="http://localhost:3000/portal" class="btn">Go to Dashboard</a>
        </div>
        <div class="footer">
            &copy; 2026 PropertyHub Ghana. All rights reserved.
        </div>
    </div>
</body>
</html>
