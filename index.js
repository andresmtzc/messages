const express = require('express');
const axios = require('axios');
const bodyParser = require('body-parser');

const app = express();
app.use(bodyParser.json());

const SUPABASE_URL = 'https://pmcfepoldulhtswwtpkg.supabase.co';
const SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBtY2ZlcG9sZHVsaHRzd3d0cGtnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTE5MzI5MDcsImV4cCI6MjA2NzUwODkwN30.1hzthlKgqNoNrcIIxaImjw19hIRp5WtY4JhNhcOou_o';

app.post('/telegram-webhook', async (req, res) => {
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
      await axios.post(`${SUPABASE_URL}/rest/v1/messages`, payload, {
        headers: {
          apikey: SUPABASE_KEY,
          Authorization: `Bearer ${SUPABASE_KEY}`,
          'Content-Type': 'application/json',
          Prefer: 'return=representation'
        }
      });

      console.log("✅ Message saved to Supabase:", payload);
    } catch (error) {
      console.error("❌ Error saving to Supabase:", error.response?.data || error.message);
    }
  }

  res.sendStatus(200);
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`🚀 Server listening on port ${PORT}`));
