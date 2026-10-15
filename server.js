const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const PUBLIC_DIR = __dirname;

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.mp4': 'video/mp4',
  '.webm': 'video/webm',
  '.ogg': 'video/ogg',
  '.wav': 'audio/wav',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
};

const server = http.createServer((req, res) => {
  // Normalize URL and remove query strings
  let decodedUrl;
  try {
    decodedUrl = decodeURIComponent(req.url.split('?')[0]);
  } catch (e) {
    res.statusCode = 400;
    return res.end('Bad Request');
  }

  // Handle API endpoint to persist videos.json locally
  if (req.method === 'POST' && decodedUrl === '/api/save-videos') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        if (Array.isArray(data)) {
          fs.writeFileSync(path.join(PUBLIC_DIR, 'videos.json'), JSON.stringify(data, null, 2), 'utf8');
          res.writeHead(200, { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' });
          return res.end(JSON.stringify({ success: true }));
        }
      } catch (e) {
        console.error('Error writing videos.json:', e);
      }
      res.writeHead(400, { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' });
      res.end(JSON.stringify({ error: 'Invalid data' }));
    });
    return;
  }

  let filePath = path.join(PUBLIC_DIR, decodedUrl);

  // Default to index.html if root or directory
  if (decodedUrl === '/' || decodedUrl === '') {
    filePath = path.join(PUBLIC_DIR, 'index.html');
  } else {
    try {
      if (fs.existsSync(filePath) && fs.statSync(filePath).isDirectory()) {
        filePath = path.join(filePath, 'index.html');
      }
    } catch (_) {}
  }

  // Security check to avoid directory traversal
  const relative = path.relative(PUBLIC_DIR, filePath);
  if (relative.startsWith('..') || path.isAbsolute(relative) === false && relative.startsWith('\\\\')) {
    res.statusCode = 403;
    return res.end('Forbidden');
  }

  fs.stat(filePath, (err, stats) => {
    if (err || !stats.isFile()) {
      res.statusCode = 404;
      res.setHeader('Content-Type', 'text/plain; charset=utf-8');
      return res.end('404 Not Found');
    }

    const ext = path.extname(filePath).toLowerCase();
    const contentType = MIME_TYPES[ext] || 'application/octet-stream';

    // Support HTTP Range headers for smooth video streaming and seeking
    const range = req.headers.range;
    if (range && stats.size > 0) {
      const parts = range.replace(/bytes=/, "").split("-");
      const start = parseInt(parts[0], 10);
      const end = parts[1] ? parseInt(parts[1], 10) : stats.size - 1;

      if (start >= stats.size || end >= stats.size) {
        res.writeHead(416, {
          'Content-Range': `bytes */${stats.size}`
        });
        return res.end();
      }

      const chunksize = (end - start) + 1;
      const file = fs.createReadStream(filePath, { start, end });
      res.writeHead(206, {
        'Content-Range': `bytes ${start}-${end}/${stats.size}`,
        'Accept-Ranges': 'bytes',
        'Content-Length': chunksize,
        'Content-Type': contentType,
        'Access-Control-Allow-Origin': '*'
      });
      file.pipe(res);
    } else {
      res.writeHead(200, {
        'Content-Length': stats.size,
        'Content-Type': contentType,
        'Accept-Ranges': 'bytes',
        'Access-Control-Allow-Origin': '*'
      });
      fs.createReadStream(filePath).pipe(res);
    }
  });
});

server.listen(PORT, () => {
  console.log(`Server running at http://localhost:${PORT}/`);
});

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    const nextPort = Number(PORT) + 1;
    console.log(`Port ${PORT} is in use, retrying on port ${nextPort}...`);
    server.listen(nextPort);
  } else {
    console.error('Server error:', err);
  }
});
