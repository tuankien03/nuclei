const express = require('express');
const app = express();
const port = 8081;


app.get('/test', (req, res) => {
  console.log(`[+] nhận request:: http://localhost:${port}/test?pr=`,req.query.pr);
//   console.log(`\nquery: `, req.query);
//   console.log(`\nparams: `, req.params);
  res.send('OK');
});


app.post('/test', (req, res) => {
  console.log(`[+] nhận request:: http://localhost:${port}/test?pr=`,req.query.pr);
  console.log(`\nquery: `, req.query);
  console.log(`\nparams: `, req.params);
  res.send('OK');
}
);


app.listen(port, () => {
  console.log(`Server đang chạy tại http://localhost:${port}`);
});
