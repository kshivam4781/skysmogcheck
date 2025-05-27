import http.server
import socketserver

PORT = 8080
DIRECTORY = "src"

class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIRECTORY, **kwargs)

with socketserver.TCPServer(("", PORT), Handler) as httpd:
    print(f"Serving at http://localhost:{PORT}")
    print(f"Open your browser and go to: http://localhost:{PORT}/pages/index.html")
    httpd.serve_forever() 