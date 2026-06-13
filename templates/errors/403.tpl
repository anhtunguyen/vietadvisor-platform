<!DOCTYPE html>
<html lang="{$current_lang|default:'en'}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$page_title|default:'Error 403 - Access Denied'}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; text-align: center; padding: 100px 20px; margin: 0; }
        .error-container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-top: 4px solid #d97706; }
        h1 { color: #d97706; font-size: 72px; margin: 0 0 10px 0; font-weight: 800; line-height: 1; }
        h2 { font-size: 22px; margin-bottom: 20px; color: #343a40; font-weight: 600; }
        p { color: #6c757d; line-height: 1.6; margin-bottom: 30px; font-size: 15px; text-align: justify; text-justify: inter-word; }
        .btn { display: inline-block; background: #0056b3; color: white; padding: 12px 30px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 14px; transition: background 0.2s ease; box-shadow: 0 2px 4px rgba(0, 86, 179, 0.2); }
        .btn:hover { background: #003d82; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>403</h1>
        <h2>{trans key="error_403_title" default="Access Denied"}</h2>
        <p>{trans key="error_403_desc" default="You do not have permission to access this resource. Automated crawlers, malicious bots, or unauthorized sessions are strictly restricted from this zone due to cybersecurity and data protection policies."}</p>
        <a href="{$smarty.const.ROOT_URL}" class="btn">Return Homepage</a>
    </div>
</body>
</html>