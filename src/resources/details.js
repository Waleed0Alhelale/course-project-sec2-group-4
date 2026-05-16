const resourceTitle = document.getElementById('resource-title');
const resourceDescription = document.getElementById('resource-description');
const resourceLink = document.getElementById('resource-link');
const commentList = document.getElementById('comment-list');
const commentForm = document.getElementById('comment-form');
const commentInput = document.getElementById('new-comment');

let currentComments = [];

function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderResourceDetails(resource) {
  if (!resource) return;
  if (resourceTitle) resourceTitle.textContent = resource.title;
  if (resourceDescription) resourceDescription.textContent = resource.description;
  if (resourceLink) {
    resourceLink.href = resource.link;
    resourceLink.textContent = 'Access Resource Material';
  }
}

function createCommentArticle(comment) {
  const article = document.createElement('article');
  const text = document.createElement('p');
  const footer = document.createElement('footer');

  text.textContent = comment.text;
  footer.textContent = `Posted by: ${comment.author}`;

  article.appendChild(text);
  article.appendChild(footer);
  return article;
}

function renderComments() {
  if (!commentList) return;
  commentList.innerHTML = '';

  if (!Array.isArray(currentComments) || currentComments.length === 0) {
    const empty = document.createElement('p');
    empty.textContent = 'No comments yet. Be the first to comment!';
    commentList.appendChild(empty);
    return;
  }

  currentComments.forEach(comment => {
    commentList.appendChild(createCommentArticle(comment));
  });
}

async function loadResource(resourceId) {
  const response = await fetch(`./api/index.php?id=${encodeURIComponent(resourceId)}`);
  const result = await response.json();

  if (result.success && result.data) {
    renderResourceDetails(result.data);
  }
}

async function loadComments(resourceId) {
  const response = await fetch(`./api/index.php?action=comments&resource_id=${encodeURIComponent(resourceId)}`);
  const result = await response.json();

  if (result.success && Array.isArray(result.data)) {
    currentComments = result.data;
  } else {
    currentComments = [];
  }

  renderComments();
}

async function handleAddComment(event) {
  event.preventDefault();
  const resourceId = getResourceIdFromURL();
  if (!resourceId || !commentInput) return;

  const text = commentInput.value.trim();
  if (!text) return;

  const response = await fetch(`./api/index.php?action=comment`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ resource_id: resourceId, author: 'Anonymous', text }),
  });

  const result = await response.json();

  if (result.success) {
    commentInput.value = '';
    await loadComments(resourceId);
  }
}

async function initializePage() {
  const resourceId = getResourceIdFromURL();
  if (!resourceId) {
    if (resourceTitle) resourceTitle.textContent = 'Resource ID missing';
    return;
  }

  await loadResource(resourceId);
  await loadComments(resourceId);

  if (commentForm) {
    commentForm.addEventListener('submit', handleAddComment);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializePage);
} else {
  initializePage();
}
