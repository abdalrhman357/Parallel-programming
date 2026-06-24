const http = require('http');

async function test() {
  const reqs = [];
  for (let i = 0; i < 10; i++) {
    reqs.push(new Promise((resolve) => {
      const data = JSON.stringify({ user_id: i + 1 });
      const options = {
        hostname: '127.0.0.1',
        port: 8000,
        path: '/api/checkout',
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': data.length
        }
      };
      const req = http.request(options, (res) => {
        let body = '';
        res.on('data', chunk => body += chunk);
        res.on('end', () => resolve({ status: res.statusCode, body }));
      });
      req.write(data);
      req.end();
    }));
  }
  const results = await Promise.all(reqs);
  console.log(results);
}
test();
