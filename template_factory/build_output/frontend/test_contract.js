const axios = require('axios');

const testData = {
  kennelAddress: "Test Kennel Address 123",
  buyerName: "Test Buyer",
  buyerEmail: "test@example.com",
  buyerPhone: "+79001234567",
  dogName: "Test Dog",
  price: "50000"
};

axios.post('https://{{SERVER_IP}}/create_envelope.php', testData, {
  headers: {
    'X-API-KEY': '{{BRIDGE_SECRET}}',
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
