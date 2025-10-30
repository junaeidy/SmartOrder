<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 40px;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            color: #2d3748;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .title {
            color: #2d3748;
            font-size: 22px;
            margin: 0;
            padding: 0;
        }
        .code-container {
            text-align: center;
            padding: 30px 0;
            margin: 30px 0;
            background: #f8fafc;
            border-radius: 8px;
        }
        .code {
            font-size: 36px;
            letter-spacing: 8px;
            font-weight: bold;
            color: #2d3748;
            padding: 10px 20px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            display: inline-block;
        }
        .warning {
            font-size: 14px;
            color: #718096;
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        .expiry {
            text-align: center;
            color: #e53e3e;
            font-size: 16px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #718096;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">SmartOrder</div>
            <h1 class="title">Reset Password Code</h1>
        </div>

        <p style="text-align: center; color: #4a5568;">
            We received a request to reset your password. Use the code below to proceed:
        </p>

        <div class="code-container">
            <div class="code">{{ $code }}</div>
        </div>

        <div class="expiry">
            ⏰ This code will expire in 60 minutes
        </div>

        <p style="text-align: center; color: #4a5568;">
            Enter this code in the app to reset your password and regain access to your account.
        </p>

        <div class="warning">
            If you didn't request this password reset, you can safely ignore this email. Someone might have typed your email address by mistake.
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} SmartOrder. All rights reserved.<br>
            This is an automated message, please do not reply.
        </div>
    </div>
</body>
</html>