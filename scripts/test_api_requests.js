import * as api from '../src/pages/api/projects.js';

async function run() {
  try {
    const token = process.env.API_TOKEN;
    if (!token) throw new Error('API_TOKEN not set in .env');

    // Test POST
    const payload = { name: 'API Test Project ' + Date.now(), client: 'Client API', description: 'Creado por test_api_requests', status: 'draft', budget_amount: 5000, is_active: 1 };
    const reqPost = new Request('https://example.local/api/projects', { method: 'POST', headers: { 'x-api-token': token, 'content-type': 'application/json' }, body: JSON.stringify(payload) });
    const resPost = await api.POST({ request: reqPost });
    console.log('POST status', resPost.status);
    const postJson = await resPost.json().catch(() => null);
    console.log('POST body', postJson);

    const newId = postJson?.data?.id || postJson?.data?.insertId || postJson?.data?.external_id || postJson?.data?.id;

    // Test PUT (update if id present)
    if (postJson && postJson.data && postJson.data.id) {
      const updatePayload = { id: postJson.data.id, name: postJson.data.name + ' (updated)' };
      const reqPut = new Request('https://example.local/api/projects', { method: 'PUT', headers: { 'x-api-token': token, 'content-type': 'application/json' }, body: JSON.stringify(updatePayload) });
      const resPut = await api.PUT({ request: reqPut });
      console.log('PUT status', resPut.status);
      console.log('PUT body', await resPut.json().catch(() => null));

      // Test DELETE
      const reqDel = new Request('https://example.local/api/projects?id=' + encodeURIComponent(postJson.data.id), { method: 'DELETE', headers: { 'x-api-token': token } });
      const resDel = await api.DELETE({ request: reqDel });
      console.log('DELETE status', resDel.status);
      console.log('DELETE body', await resDel.json().catch(() => null));
    } else {
      console.log('No id returned from POST, skipping PUT/DELETE');
    }

    process.exit(0);
  } catch (err) {
    console.error('API test error', err);
    process.exit(1);
  }
}

run();
