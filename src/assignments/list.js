const assignmentListSection = document.getElementById("assignment-list-section");
const API_URL = "./api/index.php";

function getDescriptionPreview(description) {
  const text = String(description || "").trim();

  if (text.length <= 140) {
    return text;
  }

  return text.slice(0, 137) + "...";
}

function createAssignmentArticle(assignment) {
  const article = document.createElement("article");

  const title = document.createElement("h2");
  title.textContent = assignment.title || "Untitled Assignment";

  const dueDate = document.createElement("p");
  dueDate.textContent = "Due: " + (assignment.due_date || "Not set");

  const description = document.createElement("p");
  description.textContent = getDescriptionPreview(assignment.description);

  const detailsLink = document.createElement("a");
  detailsLink.href = "details.html?id=" + encodeURIComponent(assignment.id);
  detailsLink.textContent = "View Details & Discussion";

  article.append(title, dueDate, description, detailsLink);
  return article;
}

async function loadAssignments() {
  if (!assignmentListSection) {
    return;
  }

  assignmentListSection.innerHTML = "<p>Loading assignments...</p>";

  try {
    const response = await fetch(API_URL);
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load assignments.");
    }

    assignmentListSection.innerHTML = "";

    if (!Array.isArray(result.data) || result.data.length === 0) {
      assignmentListSection.innerHTML = "<p>No assignments have been posted yet.</p>";
      return;
    }

    result.data.forEach(function (assignment) {
      assignmentListSection.appendChild(createAssignmentArticle(assignment));
    });
  } catch (error) {
    assignmentListSection.innerHTML = "";

    const message = document.createElement("p");
    message.textContent = error.message || "Unable to load assignments.";
    assignmentListSection.appendChild(message);
  }
}

loadAssignments();
