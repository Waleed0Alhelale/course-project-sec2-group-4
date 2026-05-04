let currentAssignmentId = null;
let currentComments = [];

const API_URL = "./api/index.php";
const assignmentTitle = document.getElementById("assignment-title");
const assignmentDueDate = document.getElementById("assignment-due-date");
const assignmentDescription = document.getElementById("assignment-description");
const assignmentFilesList = document.getElementById("assignment-files-list");
const commentList = document.getElementById("comment-list");
const commentForm = document.getElementById("comment-form");
const commentAuthorInput = document.getElementById("comment-author");
const newCommentInput = document.getElementById("new-comment");

function getAssignmentIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function normalizeFiles(files) {
  if (Array.isArray(files)) {
    return files;
  }

  if (typeof files === "string" && files.trim() !== "") {
    try {
      const parsedFiles = JSON.parse(files);
      return Array.isArray(parsedFiles) ? parsedFiles : [];
    } catch (error) {
      return [];
    }
  }

  return [];
}

function renderAssignmentDetails(assignment) {
  assignmentTitle.textContent = assignment.title || "Untitled Assignment";
  assignmentDueDate.textContent = "Due: " + (assignment.due_date || "Not set");
  assignmentDescription.textContent = assignment.description || "";
  assignmentFilesList.innerHTML = "";

  const files = normalizeFiles(assignment.files);

  if (files.length === 0) {
    const item = document.createElement("li");
    item.textContent = "No files attached.";
    assignmentFilesList.appendChild(item);
    return;
  }

  files.forEach(function (url) {
    const item = document.createElement("li");
    const link = document.createElement("a");
    link.href = url;
    link.textContent = url;
    link.target = "_blank";
    link.rel = "noopener";
    item.appendChild(link);
    assignmentFilesList.appendChild(item);
  });
}

function createCommentArticle(comment) {
  const article = document.createElement("article");

  const text = document.createElement("p");
  text.textContent = comment.text || "";

  const footer = document.createElement("footer");
  footer.textContent = "Posted by: " + (comment.author || "Anonymous");

  article.append(text, footer);
  return article;
}

function renderComments() {
  commentList.innerHTML = "";

  if (!Array.isArray(currentComments) || currentComments.length === 0) {
    const emptyMessage = document.createElement("p");
    emptyMessage.textContent = "No comments yet.";
    commentList.appendChild(emptyMessage);
    return;
  }

  currentComments.forEach(function (comment) {
    commentList.appendChild(createCommentArticle(comment));
  });
}

async function handleAddComment(event) {
  event.preventDefault();

  const authorValue = commentAuthorInput ? commentAuthorInput.value.trim() : "";
  const author = authorValue || "Student";
  const commentText = newCommentInput.value.trim();

  if (commentText === "") {
    return;
  }

  try {
    const response = await fetch(API_URL + "?action=comment", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        assignment_id: Number(currentAssignmentId),
        author: author,
        text: commentText
      })
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to add comment.");
    }

    currentComments.push(result.data);
    renderComments();
    newCommentInput.value = "";
  } catch (error) {
    alert(error.message || "Unable to add comment.");
  }
}

async function initializePage() {
  currentAssignmentId = getAssignmentIdFromURL();

  if (!currentAssignmentId) {
    assignmentTitle.textContent = "Assignment not found.";
    return;
  }

  try {
    const assignmentUrl = API_URL + "?id=" + encodeURIComponent(currentAssignmentId);
    const commentsUrl = API_URL + "?action=comments&assignment_id=" + encodeURIComponent(currentAssignmentId);

    const responses = await Promise.all([
      fetch(assignmentUrl),
      fetch(commentsUrl)
    ]);

    const assignmentResult = await responses[0].json();
    const commentsResult = await responses[1].json();

    if (!responses[0].ok || !assignmentResult.success || !assignmentResult.data) {
      assignmentTitle.textContent = "Assignment not found.";
      return;
    }

    const assignment = assignmentResult.data;
    currentComments = Array.isArray(commentsResult.data)
      ? commentsResult.data
      : normalizeFiles(assignment.comments);

    renderAssignmentDetails(assignment);
    renderComments();
    commentForm.addEventListener("submit", handleAddComment);
  } catch (error) {
    assignmentTitle.textContent = "Assignment not found.";
  }
}

initializePage();
