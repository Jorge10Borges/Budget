(async () => {
  try {
    const res = await fetch('http://192.168.1.200:4322/api/connection?token=dev_token_change_me');
    console.log('STATUS', res.status);
    const text = await res.text();
    console.log(text);
  } catch (err) {
    console.error('ERROR', err);
  }
})();
