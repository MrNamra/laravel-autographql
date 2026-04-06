<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoGraphQL Playground</title>
    <link rel="stylesheet" href="https://unpkg.com/graphiql/graphiql.min.css" />
    <style>
        body {
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        #graphiql {
            height: 100vh;
        }
    </style>
</head>
<body>
    <div id="graphiql">Loading...</div>
    <script src="https://unpkg.com/react/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/graphiql/graphiql.min.js"></script>

    <script>
        const fetcher = GraphiQL.createFetcher({
            url: "{{ $endpoint }}",
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });

        ReactDOM.render(
            React.createElement(GraphiQL, {
                fetcher: fetcher,
                defaultVariableEditorOpen: true,
            }),
            document.getElementById('graphiql'),
        );
    </script>
</body>
</html>
