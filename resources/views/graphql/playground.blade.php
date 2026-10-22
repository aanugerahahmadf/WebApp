<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GraphQL Playground - Wedding Flower Decoration App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/graphql-playground-react@1.7.28/build/static/css/index.css" />
    <link rel="shortcut icon" href="https://cdn.jsdelivr.net/npm/graphql-playground-react@1.7.28/build/favicon.png" />
    <script src="https://cdn.jsdelivr.net/npm/graphql-playground-react@1.7.28/build/static/js/middleware.js"></script>
</head>
<body>
    <div id="root"></div>
    <script>
        window.addEventListener('DOMContentLoaded', function(event) {
            GraphQLPlayground.init(document.getElementById('root'), {
                endpoint: '{{ $endpoint }}',
                subscriptionEndpoint: '{{ $subscriptionEndpoint }}',
                settings: {
                    'request.credentials': 'include',
                },
            });
        });
    </script>
</body>
</html>