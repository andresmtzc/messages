const express = require('express');
const axios = require('axios');
const bodyParser = require('body-parser');

const app = express();
app.use(bodyParser.json());

// Use environment variables for security and flexibility
const SUPABASE_URL = process.env.SUPABASE_URL;
const SUPABASE_KEY = process.env.SUPABASE_KEY;

app.post('/telegram-webhook', async (req, res) => {
  console.log('Webhook hit with body:', JSON.stringify(req.body));

  const update = req.body;

  if (update.message) {
    const { chat, from, text, date } = update.message;

    const payload = {
      chat_id: chat.id.toString(),
      from: from.username || from.first_name || "unknown",
      text: text || "",
      timestamp: new Date(date * 1000).toISOString(),
      direction: "incoming"
    };

    try {
      const response = await axios.post(`${SUPABASE_URL}/rest/v1/messages`, payload, {
        headers: {
          apikey: SUPABASE_KEY,
          Authorization: `Bearer ${SUPABASE_KEY}`,
          'Content-Type': 'application/json',
          Prefer: 'return=representation'
        }
      });

      console.log("✅ Message saved to Supabase:", response.data);
    } catch (error) {
      console.error("❌ Error saving to Supabase:", error.response?.data || error.message);
    }
  }

  res.sendStatus(200);
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`🚀 Server listening on port ${PORT}`));
