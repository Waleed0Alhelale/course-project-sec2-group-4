/*
  Requirement: Make the "Manage Weekly Breakdown" page interactive.

  Instructions:
  1. This file is already linked to `admin.html` via:
         <script src="admin.js" defer></script>

  2. In `admin.html`:
     - The form has id="week-form".
     - The submit button has id="add-week".
     - The <tbody> has id="weeks-tbody".
     - Columns rendered per row: Week Title | Start Date | Description | Actions.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  All requests and responses use JSON.
  Successful list response shape: { success: true, data: [ ...week objects ] }
  Each week object shape:
    {
      id:          number,   // integer primary key from the weeks table
      title:       string,
      start_date:  string,   // "YYYY-MM-DD"
      description: string,
      links:       string[]  // decoded array of URL strings
    }
*/

// --- Global Data Store ---
// Holds the weeks currently displayed in the table.
let weeks = [];

// --- Element Selections ---
const API_URL = "./api/index.php";
const weekForm = document.getElementById("week-form");
const weeksTbody = document.getElementById("weeks-tbody");
const submitButton = document.getElementById("add-week");
const formHeading = document.getElementById("form-heading");
const titleInput = document.getElementById("week-title");
const startDateInput = document.getElementById("week-start-date");
const descriptionInput = document.getElementById("week-description");
const linksInput = document.getElementById("week-links");

function normalizeLinks(links) {
  return Array.isArray(links) ? links : [];
}

function getLinksFromTextarea() {
  return linksInput.value
    .split(/\r?\n/)
    .map(function (url) {
      return url.trim();
    })
    .filter(function (url) {
      return url !== "";
    });
}

function resetFormMode() {
  weekForm.reset();
  delete submitButton.dataset.editId;
  submitButton.textContent = "Add Week";

  if (formHeading) {
    formHeading.textContent = "Add a New Week";
  }
}

function getFormFields() {
  return {
    title: titleInput.value.trim(),
    start_date: startDateInput.value,
    description: descriptionInput.value.trim(),
    links: getLinksFromTextarea()
  };
}

// --- Functions ---

/**
 * TODO: Implement createWeekRow.
 *
 * Parameters:
 *   week — one week object with shape:
 *     { id, title, start_date, description, links }
 *
 * Returns a <tr> element with four <td>s:
 *   1. title
 *   2. start_date  (the "YYYY-MM-DD" string from the weeks table)
 *   3. description
 *   4. Actions — two buttons:
 *        <button class="edit-btn"   data-id="{id}">Edit</button>
 *        <button class="delete-btn" data-id="{id}">Delete</button>
 *      The data-id holds the integer primary key from the weeks table.
 */
function createWeekRow(week) {
  const row = document.createElement("tr");

  const titleCell = document.createElement("td");
  titleCell.textContent = week.title || "";

  const startDateCell = document.createElement("td");
  startDateCell.textContent = week.start_date || "";

  const descriptionCell = document.createElement("td");
  descriptionCell.textContent = week.description || "";

  const actionsCell = document.createElement("td");

  const editButton = document.createElement("button");
  editButton.className = "edit-btn";
  editButton.dataset.id = week.id;
  editButton.textContent = "Edit";

  const deleteButton = document.createElement("button");
  deleteButton.className = "delete-btn";
  deleteButton.dataset.id = week.id;
  deleteButton.textContent = "Delete";

  actionsCell.append(editButton, deleteButton);
  row.append(titleCell, startDateCell, descriptionCell, actionsCell);

  return row;

}

/**
 * TODO: Implement renderTable.
 *
 * It should:
 * 1. Clear the weeks table body (set innerHTML to "").
 * 2. Loop through the global `weeks` array.
 * 3. For each week, call createWeekRow(week) and append the <tr>
 *    to the table body.
 */
function renderTable() {
  weeksTbody.innerHTML = "";

  weeks.forEach(function (week) {
    weeksTbody.appendChild(createWeekRow(week));
  });
}

/**
 * TODO: Implement handleAddWeek (async).
 *
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Call event.preventDefault().
 * 2. Read values from:
 *      - #week-title       → title (string)
 *      - #week-start-date  → start_date (string, "YYYY-MM-DD")
 *      - #week-description → description (string)
 *      - #week-links       → split by newlines (\n) and filter empty
 *                            strings to produce a links array.
 * 3. Check if the submit button (#add-week) has a data-edit-id attribute.
 *    - If it does, call handleUpdateWeek() with that id and the field values.
 *    - If it does not, send a POST to './api/index.php' with the body:
 *        { title, start_date, description, links }
 *      On success (result.success === true):
 *        - Add the new week (with the id from result.id) to the global
 *          `weeks` array.
 *        - Call renderTable().
 *        - Reset the form.
 */
async function handleAddWeek(event) {
  event.preventDefault();

  const fields = getFormFields();
  const editId = submitButton.dataset.editId;

  if (editId) {
    await handleUpdateWeek(Number(editId), fields);
    return;
  }

  try {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(fields)
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to add week.");
    }

    const newWeek = result.data || {
      id: result.id,
      title: fields.title,
      start_date: fields.start_date,
      description: fields.description,
      links: fields.links
    };

    weeks.push(newWeek);
    renderTable();
    resetFormMode();
  } catch (error) {
    alert(error.message || "Unable to add week.");
  }
}

/**
 * Implement handleUpdateWeek (async).
 *
 * Parameters:
 *   id     — the integer primary key of the week being edited.
 *   fields — object with { title, start_date, description, links }.
 *
 * It should:
 * 1. Send a PUT to './api/index.php' with the body:
 *      { id, title, start_date, description, links }
 * 2. On success:
 *    - Update the matching entry in the global `weeks` array.
 *    - Call renderTable().
 *    - Reset the form.
 *    - Restore the submit button text to "Add Week" and remove
 *      its data-edit-id attribute.
 */
async function handleUpdateWeek(id, fields) {
  try {
    const response = await fetch(API_URL, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        id: id,
        title: fields.title,
        start_date: fields.start_date,
        description: fields.description,
        links: fields.links
      })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to update week.");
    }

    const updatedWeek = result.data || {
      id: id,
      title: fields.title,
      start_date: fields.start_date,
      description: fields.description,
      links: fields.links
    };

    weeks = weeks.map(function (week) {
      return Number(week.id) === Number(id) ? updatedWeek : week;
    });

    renderTable();
    resetFormMode();
  } catch (error) {
    alert(error.message || "Unable to update week.");
  }
}

/**
 * Implement handleTableClick (async).
 *
 * This is a delegated click listener on the weeks table body.
 * It should:
 * 1. If event.target has class "delete-btn":
 *    a. Read the integer id from event.target.dataset.id.
 *    b. Send a DELETE to './api/index.php?id=<id>'.
 *    c. On success, remove the week from the global `weeks` array
 *       and call renderTable().
 *
 * 2. If event.target has class "edit-btn":
 *    a. Read the integer id from event.target.dataset.id.
 *    b. Find the matching week in the global `weeks` array.
 *    c. Populate the form fields (#week-title, #week-start-date,
 *       #week-description, #week-links) with the week's data.
 *       For #week-links, join the links array with newlines (\n).
 *    d. Change the submit button (#add-week) text to "Update Week"
 *       and set its data-edit-id attribute to the week's id.
 */
async function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = Number(event.target.dataset.id);

    if (!confirm("Delete this week?")) {
      return;
    }

    try {
      const response = await fetch(API_URL + "?id=" + encodeURIComponent(id), {
        method: "DELETE"
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Unable to delete week.");
      }

      weeks = weeks.filter(function (week) {
        return Number(week.id) !== id;
      });

      renderTable();
      resetFormMode();
    } catch (error) {
      alert(error.message || "Unable to delete week.");
    }
  }

  if (event.target.classList.contains("edit-btn")) {
    const id = Number(event.target.dataset.id);
    const week = weeks.find(function (item) {
      return Number(item.id) === id;
    });

    if (!week) {
      return;
    }

    titleInput.value = week.title || "";
    startDateInput.value = week.start_date || "";
    descriptionInput.value = week.description || "";
    linksInput.value = normalizeLinks(week.links).join("\n");

    submitButton.textContent = "Update Week";
    submitButton.dataset.editId = week.id;

    if (formHeading) {
      formHeading.textContent = "Edit Week";
    }
  }
}

/**
 * Implement loadAndInitialize (async).
 *
 * It should:
 * 1. Send a GET to './api/index.php'.
 *    Response shape: { success: true, data: [ ...week objects ] }
 * 2. Store the data array in the global `weeks` variable.
 * 3. Call renderTable() to populate the table.
 * 4. Attach the 'submit' event listener to the week form
 *    (calls handleAddWeek).
 * 5. Attach a 'click' event listener to the weeks table body
 *    (calls handleTableClick — event delegation for edit and delete).
 */
async function loadAndInitialize() {
  try {
    const response = await fetch(API_URL);
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load weeks.");
    }

    weeks = Array.isArray(result.data) ? result.data : [];
    renderTable();

    weekForm.addEventListener("submit", handleAddWeek);
    weeksTbody.addEventListener("click", handleTableClick);
  } catch (error) {
    weeksTbody.innerHTML = "";

    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 4;
    cell.textContent = error.message || "Unable to load weeks.";
    row.appendChild(cell);
    weeksTbody.appendChild(row);
  }
}

// --- Initial Page Load ---
loadAndInitialize();
