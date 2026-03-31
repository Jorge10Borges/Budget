import * as api from '../src/pages/api/projects.server.js';

async function run() {
  try {
    const argId = process.argv[2];

    // If an ID is provided, we'll update that project. Otherwise create one, update it, and (optionally) delete it.
    let projectId = argId;

    if (!projectId) {
      console.log('No project id provided — creating a new test project');
      const payload = {
        name: 'Edit Test Project ' + Date.now(),
        client: 'Test Client',
        description: 'Creado por scripts/test_edit_project.js',
        status: 'draft',
        budget_amount: 1234.5,
        currency: 'USD',
        is_active: 1
      };
      const reqPost = new Request('https://example.local/api/projects/server', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const resPost = await api.POST({ request: reqPost });
      const postJson = await resPost.json().catch(() => null);
      console.log('POST status', resPost.status);
      console.log('POST body', postJson);
      projectId = postJson?.data?.id;
      if (!projectId) throw new Error('Failed to create test project');
    } else {
      console.log('Using provided project id:', projectId);
    }

    // Now perform the edit (PUT)
    const updatePayload = {
      id: projectId,
      name: 'Edit Test Project (updated) ' + Date.now(),
      budget_amount: 9999.99,
      client: 'Updated Client'
    };
    const reqPut = new Request('https://example.local/api/projects/server', {
      method: 'PUT',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(updatePayload)
    });
    const resPut = await api.PUT({ request: reqPut });
    const putJson = await resPut.json().catch(() => null);
    console.log('PUT status', resPut.status);
    console.log('PUT body', putJson);

    // Optional: verify by fetching from DB via API module (select)
    // The server module doesn't expose a GET, so we can re-check via the returned PUT body

    // Cleanup: delete the created project if we created it in this test
    if (!argId) {
      const reqDel = new Request('https://example.local/api/projects/server?id=' + encodeURIComponent(projectId), {
        method: 'DELETE'
      });
      const resDel = await api.DELETE({ request: reqDel });
      console.log('DELETE status', resDel.status);
      console.log('DELETE body', await resDel.json().catch(() => null));
    }

    process.exit(0);
  } catch (err) {
    console.error('test_edit_project error', err);
    process.exit(1);
  }
}

run();
