let resources = [];

let resourceForm;
let resourcesTbody;

function createResourceRow(resource) {
  const { id, title, description, link } = resource;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${title}</td>
    <td>${description}</td>
    <td>${link}</td>
    <td>
      <button class="edit-btn" data-id="${id}">Edit</button>
      <button class="delete-btn" data-id="${id}">Delete</button>
    </td>
  `;
  return tr;
}

function renderTable(data = null) {
  if (!resourcesTbody) return;
  resourcesTbody.innerHTML = '';

  if (Array.isArray(data)) {
    resources = data;
  }

  const list = Array.isArray(data) ? data : resources;
  list.forEach(resource => {
    resourcesTbody.appendChild(createResourceRow(resource));
  });
}

async function handleAddResource(event) {
  event.preventDefault();

  const title       = document.querySelector('#resource-title').value;
  const description = document.querySelector('#resource-description').value;
  const link        = document.querySelector('#resource-link').value;

  const response = await fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, description, link })
  });

  const result = await response.json();

  if (result.success) {
    resources.push({ id: result.id, title, description, link });
    renderTable();
    resourceForm.reset();
  }
}

function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;

    fetch(`./api/index.php?id=${id}`, { method: 'DELETE' })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          resources = resources.filter(r => r.id != id);
          renderTable();
        }
      });
  }

  if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;
    const resource = resources.find(r => r.id == id);

    document.querySelector('#resource-title').value       = resource.title;
    document.querySelector('#resource-description').value = resource.description;
    document.querySelector('#resource-link').value        = resource.link;

    const submitBtn = document.querySelector('#add-resource');
    submitBtn.textContent = 'Update Resource';

    const updateHandler = async (event) => {
      event.preventDefault();

      const title       = document.querySelector('#resource-title').value;
      const description = document.querySelector('#resource-description').value;
      const link        = document.querySelector('#resource-link').value;

      const response = await fetch('./api/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, title, description, link })
      });

      const result = await response.json();

      if (result.success) {
        const index = resources.findIndex(r => r.id == id);
        resources[index] = { id, title, description, link };
        renderTable();
        resourceForm.reset();
        submitBtn.textContent = 'Add Resource';
        resourceForm.removeEventListener('submit', updateHandler);
        resourceForm.addEventListener('submit', handleAddResource);
      }
    };

    resourceForm.removeEventListener('submit', handleAddResource);
    resourceForm.addEventListener('submit', updateHandler);
  }
}

async function loadAndInitialize() {
  resourceForm    = document.querySelector('#resource-form');
  resourcesTbody  = document.querySelector('#resources-tbody');

  const response = await fetch('./api/index.php');
  const result   = await response.json();

  if (result.success) {
    resources = result.data;
    renderTable();
  }

  if (resourceForm)    resourceForm.addEventListener('submit', handleAddResource);
  if (resourcesTbody)  resourcesTbody.addEventListener('click', handleTableClick);
}

if (typeof document !== 'undefined' && document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', loadAndInitialize);
} else if (typeof document !== 'undefined') {
  loadAndInitialize();
}
