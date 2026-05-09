/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// This will hold the resources loaded from the API.
let resources = [];

// --- Element Selections ---
const resourceForm = document.querySelector('#resource-form');
const resourcesTbody = document.querySelector('#resources-tbody');

// --- Helper: show a brief toast notification ---
function showToast(message, isError = false) {
  const toast = document.querySelector('#toast');
  if (!toast) return;
  toast.textContent = message;
  toast.style.background = isError ? 'var(--danger)' : 'var(--accent)';
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}

// --- Helper: set the form into "Add" mode ---
function resetFormToAddMode() {
  resourceForm.reset();
  resourceForm.removeAttribute('data-edit-id');

  const submitBtn = document.querySelector('#add-resource');
  submitBtn.textContent = 'Add Resource';

  const cancelBtn = document.querySelector('#cancel-edit');
  if (cancelBtn) cancelBtn.style.display = 'none';

  const formHeading = document.querySelector('#form-heading');
  if (formHeading) formHeading.textContent = 'Add a New Resource';

  const formStatus = document.querySelector('#form-status');
  if (formStatus) formStatus.textContent = '';
}

// --- Functions ---

/**
 * createResourceRow
 * Takes one resource object { id, title, description, link }.
 * Returns a <tr> element ready to be appended to the table.
 */
function createResourceRow(resource) {
  const { id, title, description, link } = resource;

  const tr = document.createElement('tr');

  // Title cell
  const tdTitle = document.createElement('td');
  tdTitle.className = 'td-title';
  tdTitle.textContent = title;

  // Description cell
  const tdDesc = document.createElement('td');
  tdDesc.className = 'td-desc';
  tdDesc.textContent = description || '—';

  // Link cell
  const tdLink = document.createElement('td');
  tdLink.className = 'td-link';
  const anchor = document.createElement('a');
  anchor.href = link;
  anchor.textContent = link;
  anchor.target = '_blank';
  anchor.rel = 'noopener noreferrer';
  tdLink.appendChild(anchor);

  // Actions cell
  const tdActions = document.createElement('td');
  const actionsWrapper = document.createElement('div');
  actionsWrapper.className = 'td-actions';

  const editBtn = document.createElement('button');
  editBtn.textContent = 'Edit';
  editBtn.className = 'btn btn-warning edit-btn';
  editBtn.dataset.id = id;
  editBtn.type = 'button';

  const deleteBtn = document.createElement('button');
  deleteBtn.textContent = 'Delete';
  deleteBtn.className = 'btn btn-danger delete-btn';
  deleteBtn.dataset.id = id;
  deleteBtn.type = 'button';

  actionsWrapper.appendChild(editBtn);
  actionsWrapper.appendChild(deleteBtn);
  tdActions.appendChild(actionsWrapper);

  tr.appendChild(tdTitle);
  tr.appendChild(tdDesc);
  tr.appendChild(tdLink);
  tr.appendChild(tdActions);

  return tr;
}

/**
 * renderTable
 * Clears the table body and re-renders all rows from the global `resources` array.
 */
function renderTable() {
  resourcesTbody.innerHTML = '';

  if (resources.length === 0) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 4;
    td.innerHTML = '<div class="empty-state"><p>No resources yet. Add one above.</p></div>';
    tr.appendChild(td);
    resourcesTbody.appendChild(tr);
    return;
  }

  resources.forEach(resource => {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}

/**
 * handleAddResource
 * Handles the form submit event for both adding and updating a resource.
 */
async function handleAddResource(event) {
  event.preventDefault();

  const title       = document.querySelector('#resource-title').value.trim();
  const description = document.querySelector('#resource-description').value.trim();
  const link        = document.querySelector('#resource-link').value.trim();

  // Check if we are in edit mode (form has a data-edit-id attribute)
  const editId = resourceForm.getAttribute('data-edit-id');

  if (editId) {
    // ── UPDATE mode ────────────────────────────────────────────────────────
    try {
      const response = await fetch('./api/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: editId, title, description, link }),
      });

      if (!response.ok) throw new Error(`Server error: ${response.status}`);

      const result = await response.json();

      if (result.success) {
        // Update the matching resource in the global array
        const index = resources.findIndex(r => String(r.id) === String(editId));
        if (index !== -1) {
          resources[index] = { id: Number(editId), title, description, link };
        }

        renderTable();
        resetFormToAddMode();
        showToast('Resource updated successfully.');
      } else {
        showToast(result.message || 'Failed to update resource.', true);
      }
    } catch (err) {
      console.error('Update error:', err);
      showToast('An error occurred while updating. Please try again.', true);
    }

  } else {
    // ── ADD mode ───────────────────────────────────────────────────────────
    try {
      const response = await fetch('./api/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, description, link }),
      });

      if (!response.ok) throw new Error(`Server error: ${response.status}`);

      const result = await response.json();

      if (result.success) {
        // Add new resource (using the id returned by the API) to the global array
        resources.push({ id: result.id, title, description, link });

        renderTable();
        resourceForm.reset();
        showToast('Resource added successfully.');
      } else {
        showToast(result.message || 'Failed to add resource.', true);
      }
    } catch (err) {
      console.error('Add error:', err);
      showToast('An error occurred while adding. Please try again.', true);
    }
  }
}

/**
 * handleTableClick
 * Event-delegation handler for clicks on the table body.
 * Handles both "Delete" and "Edit" button clicks.
 */
async function handleTableClick(event) {
  const target = event.target;

  // ── DELETE ──────────────────────────────────────────────────────────────
  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;

    if (!confirm('Are you sure you want to delete this resource? This cannot be undone.')) {
      return;
    }

    try {
      const response = await fetch(`./api/index.php?id=${id}`, {
        method: 'DELETE',
      });

      if (!response.ok) throw new Error(`Server error: ${response.status}`);

      const result = await response.json();

      if (result.success) {
        // Remove the resource from the global array
        resources = resources.filter(r => String(r.id) !== String(id));
        renderTable();
        showToast('Resource deleted successfully.');

        // If we were editing this resource, reset the form
        if (resourceForm.getAttribute('data-edit-id') === String(id)) {
          resetFormToAddMode();
        }
      } else {
        showToast(result.message || 'Failed to delete resource.', true);
      }
    } catch (err) {
      console.error('Delete error:', err);
      showToast('An error occurred while deleting. Please try again.', true);
    }
  }

  // ── EDIT ────────────────────────────────────────────────────────────────
  if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;
    const resource = resources.find(r => String(r.id) === String(id));

    if (!resource) return;

    // Populate form fields with the resource's current values
    document.querySelector('#resource-title').value       = resource.title;
    document.querySelector('#resource-description').value = resource.description || '';
    document.querySelector('#resource-link').value        = resource.link;

    // Mark the form as being in edit mode
    resourceForm.setAttribute('data-edit-id', id);

    // Update UI to indicate edit mode
    const submitBtn = document.querySelector('#add-resource');
    submitBtn.textContent = 'Update Resource';

    const cancelBtn = document.querySelector('#cancel-edit');
    if (cancelBtn) cancelBtn.style.display = 'inline-flex';

    const formHeading = document.querySelector('#form-heading');
    if (formHeading) formHeading.textContent = 'Edit Resource';

    const formStatus = document.querySelector('#form-status');
    if (formStatus) formStatus.textContent = `Editing: "${resource.title}"`;

    // Scroll the form into view smoothly
    resourceForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

/**
 * loadAndInitialize
 * Fetches all resources from the API, populates the table,
 * and wires up all event listeners.
 */
async function loadAndInitialize() {
  try {
    const response = await fetch('./api/index.php');

    if (!response.ok) throw new Error(`Server error: ${response.status}`);

    const result = await response.json();

    if (result.success) {
      resources = result.data;
      renderTable();
    } else {
      showToast('Failed to load resources.', true);
    }
  } catch (err) {
    console.error('Load error:', err);
    showToast('Could not connect to the API. Please check your server.', true);
  }

  // Attach form submit listener
  resourceForm.addEventListener('submit', handleAddResource);

  // Attach table click listener (event delegation)
  resourcesTbody.addEventListener('click', handleTableClick);

  // Attach Cancel button listener (resets form back to Add mode)
  const cancelBtn = document.querySelector('#cancel-edit');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', resetFormToAddMode);
  }
}

// --- Initial Page Load ---
loadAndInitialize();
