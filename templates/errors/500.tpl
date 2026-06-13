<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$page_title}</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; color: #333; text-align: center; padding: 100px 20px; }
        .error-container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h1 { color: #dc3545; font-size: 48px; margin: 0 0 10px 0; }
        h2 { font-size: 20px; margin-bottom: 20px; color: #495057; }
        p { color: #6c757d; line-height: 1.6; margin-bottom: 30px; }
        .btn { display: inline-block; background: #0056b3; color: white; padding: 10px 25px; border-radius: 4px; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>{$status_code}</h1>
        <h2>{trans key="error_500_title" default="An Unexpected Error Occurred"}</h2>
        <p>{trans key="error_500_desc" default="Our technical team has been automatically notified. We are resolving this infrastructure optimization right now. Please try again later."}</p>
        <a href="{$smarty.const.ROOT_URL}" class="btn">Return Homepage</a>
    </div>
</body>
</html>
