const axios = require('axios');

const testData = {
  kennelAddress: "Test Kennel Address 123",
  buyerName: "Test Buyer",
  buyerEmail: "test@example.com",
  buyerPhone: "+79001234567",
  dogName: "Test Dog",
  price: "50000"
};

axios.post('https://72.62.114.139/create_envelope.php', testData, {
  headers: {
    'X-API-KEY': 'matrang_secret_key_2026',
    'Content-Type': 'application/json'
  },
  httpsAgent: new (require('https')).Agent({
    rejectUnauthorized: false
  })
})
.then(response => {
  console.log('SUCCESS:', JSON.stringify(response.data, null, 2));
})
.catch(error => {
  console.error('ERROR:', error.response?.data || error.message);
});
