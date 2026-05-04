let assignments = [];

const API_URL = "./api/index.php";
const assignmentForm = document.getElementById("assignment-form");
const assignmentsTbody = document.getElementById("assignments-tbody");
const submitButton = document.getElementById("add-assignment");
const formHeading = document.getElementById("form-heading");
const titleInput = document.getElementById("assignment-title");
const dueDateInput = document.getElementById("assignment-due-date");
const descriptionInput = document.getElementById("assignment-description");
const filesInput = document.getElementById("assignment-files");

function normalizeFiles(files) {
  return Array.isArray(files) ? files : [];
}

function getFilesFromTextarea() {
  return filesInput.value
    .split(/\r?\n/)
    .map(function (url) {
      return url.trim();
    })
    .filter(function (url) {
      return url !== "";
    });
}

function createAssignmentRow(assignment) {
  const row = document.createElement("tr");

  const titleCell = document.createElement("td");
  titleCell.textContent = assignment.title || "";

  const dueDateCell = document.createElement("td");
  dueDateCell.textContent = assignment.due_date || "";

  const descriptionCell = document.createElement("td");
  descriptionCell.textContent = assignment.description || "";

  const actionsCell = document.createElement("td");

  const editButton = document.createElement("button");
  editButton.className = "edit-btn";
  editButton.dataset.id = assignment.id;
  editButton.textContent = "Edit";

  const deleteButton = document.createElement("button");
  deleteButton.className = "delete-btn";
  deleteButton.dataset.id = assignment.id;
  deleteButton.textContent = "Delete";

  actionsCell.append(editButton, deleteButton);
  row.append(titleCell, dueDateCell, descriptionCell, actionsCell);

  return row;
}

function renderTable() {
  assignmentsTbody.innerHTML = "";

  assignments.forEach(function (assignment) {
    assignmentsTbody.appendChild(createAssignmentRow(assignment));
  });
}

function resetFormMode() {
  assignmentForm.reset();
  delete submitButton.dataset.editId;
  submitButton.textContent = "Add Assignment";

  if (formHeading) {
    formHeading.textContent = "Add a New Assignment";
  }
}

function getFormFields() {
  return {
    title: titleInput.value.trim(),
    due_date: dueDateInput.value,
    description: descriptionInput.value.trim(),
    files: getFilesFromTextarea()
  };
}

async function handleAddAssignment(event) {
  event.preventDefault();

  const fields = getFormFields();
  const editId = submitButton.dataset.editId;

  if (editId) {
    await handleUpdateAssignment(Number(editId), fields);
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
      throw new Error(result.message || "Unable to add assignment.");
    }

    const newAssignment = result.data || {
      id: result.id,
      title: fields.title,
      due_date: fields.due_date,
      description: fields.description,
      files: fields.files
    };

    assignments.push(newAssignment);
    renderTable();
    resetFormMode();
  } catch (error) {
    alert(error.message || "Unable to add assignment.");
  }
}

async function handleUpdateAssignment(id, fields) {
  try {
    const response = await fetch(API_URL + "?action=update", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        id: id,
        title: fields.title,
        due_date: fields.due_date,
        description: fields.description,
        files: fields.files
      })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to update assignment.");
    }

    const updatedAssignment = result.data || {
      id: id,
      title: fields.title,
      due_date: fields.due_date,
      description: fields.description,
      files: fields.files
    };

    assignments = assignments.map(function (assignment) {
      return Number(assignment.id) === Number(id) ? updatedAssignment : assignment;
    });

    renderTable();
    resetFormMode();
  } catch (error) {
    alert(error.message || "Unable to update assignment.");
  }
}

async function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = Number(event.target.dataset.id);

    if (!confirm("Delete this assignment?")) {
      return;
    }

    try {
      const response = await fetch(API_URL + "?action=delete", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          id: id
        })
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Unable to delete assignment.");
      }

      assignments = assignments.filter(function (assignment) {
        return Number(assignment.id) !== id;
      });

      renderTable();
      resetFormMode();
    } catch (error) {
      alert(error.message || "Unable to delete assignment.");
    }
  }

  if (event.target.classList.contains("edit-btn")) {
    const id = Number(event.target.dataset.id);
    const assignment = assignments.find(function (item) {
      return Number(item.id) === id;
    });

    if (!assignment) {
      return;
    }

    titleInput.value = assignment.title || "";
    dueDateInput.value = assignment.due_date || "";
    descriptionInput.value = assignment.description || "";
    filesInput.value = normalizeFiles(assignment.files).join("\n");

    submitButton.textContent = "Update Assignment";
    submitButton.dataset.editId = assignment.id;

    if (formHeading) {
      formHeading.textContent = "Edit Assignment";
    }
  }
}

async function loadAndInitialize() {
  try {
    const response = await fetch(API_URL);
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load assignments.");
    }

    assignments = Array.isArray(result.data) ? result.data : [];
    renderTable();

    assignmentForm.addEventListener("submit", handleAddAssignment);
    assignmentsTbody.addEventListener("click", handleTableClick);
  } catch (error) {
    assignmentsTbody.innerHTML = "";

    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 4;
    cell.textContent = error.message || "Unable to load assignments.";
    row.appendChild(cell);
    assignmentsTbody.appendChild(row);
  }
}

loadAndInitialize();
