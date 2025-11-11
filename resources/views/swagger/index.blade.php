<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OM Pay API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui.css" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }

        *, *:before, *:after {
            box-sizing: inherit;
        }

        body {
            margin:0;
            background: #fafafa;
        }

        .swagger-ui .topbar {
            display: none;
        }

        .swagger-ui .info .title {
            color: #ff6b35;
        }

        .swagger-ui .scheme-container {
            background: #ff6b35;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui-bundle.js" crossorigin></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui-standalone-preset.js" crossorigin></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: '/api/docs.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                validatorUrl: null,
                tryItOutEnabled: true,
                requestInterceptor: function(request) {
                    // Add authorization header if token exists
                    const token = localStorage.getItem('api_token');
                    if (token) {
                        request.headers.Authorization = 'Bearer ' + token;
                    }
                    return request;
                }
            });
        };
    </script>
</body>
</html>