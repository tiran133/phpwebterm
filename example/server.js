import http from 'http';
import fs from 'fs';
import path from 'path';
import {fileURLToPath} from 'url';

// Simulate __dirname for ES Modules
const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Define root directory (where index.html is located)
const ROOT_DIR = __dirname;

// Define allowed directory for external assets
const EXTERNAL_ASSETS_DIR = path.resolve(__dirname, '..'); // Adjust path as needed

// Define the port to listen on
const PORT = 3000;

// Helper function to resolve safe paths
function resolveSafePath(requestedPath) {
    if (requestedPath === '/') {
        return path.join(ROOT_DIR, 'index.html'); // Default to index.html
    }

    if (requestedPath.startsWith('/dist/')) {
        return path.join(EXTERNAL_ASSETS_DIR, requestedPath.replace('/../', ''));
    }

    return path.join(ROOT_DIR, requestedPath); // Serve from ROOT_DIR by default
}

// Create the HTTP server
const server = http.createServer((req, res) => {
    const safePath = resolveSafePath(req.url);

    // Check if the resolved path is a file
    fs.stat(safePath, (err, stats) => {
        if (err || !stats.isFile()) {
            if (err && err.code === 'ENOENT') {
                res.writeHead(404, {'Content-Type': 'text/plain'});
                res.end('404 Not Found');
            } else {
                res.writeHead(403, {'Content-Type': 'text/plain'});
                res.end('403 Forbidden');
            }
            return;
        }

        // Read and serve the file
        fs.readFile(safePath, (err, content) => {
            if (err) {
                res.writeHead(500, {'Content-Type': 'text/plain'});
                res.end('500 Internal Server Error');
                console.error('Error serving file:', err);
                return;
            }

            const extname = path.extname(safePath);
            const contentType = {
                '.html': 'text/html',
                '.js': 'application/javascript',
                '.css': 'text/css',
            }[extname] || 'text/plain';

            res.writeHead(200, {'Content-Type': contentType});
            res.end(content);
        });
    });
});

// Start the server
server.listen(PORT, () => {
    console.log(`Server running at http://localhost:${PORT}`);
    console.log(`Root directory: ${ROOT_DIR}`);
    console.log(`External assets directory: ${EXTERNAL_ASSETS_DIR}`);
});
