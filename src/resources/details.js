/*
  Requirement: Populate the resource detail page and discussion forum.
*/

// --- Global Data Store ---
let currentResourceId = null;
let currentComments = [];

// --- Element Selections ---
const titleEl       = document.getElementById('resource-title');
const descriptionEl = document.getElementById('resource-description');
const linkEl        = document.getElementById('resource-link');
const commentListEl = document.getElementById('comment-list');
const commentFormEl = document.getElementById('comment-form');
const newCommentEl  = document.getElementById('new-comment');

// --- Functions ---

/**
 * Reads the 'id' query parameter from the current URL.
 * e.g. details.html?id=3  →  returns "3"
 */
function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

/**
 * Fills the page header and description with data from a resource object.
 * @param {{ id, title, description, link }} resource
 */
function renderResourceDetails(resource) {
  titleEl.textContent       = resource.title;
  descriptionEl.textContent = resource.description;
  linkEl.href               = resource.link;
}

/**
 * Builds and returns a single <article> element for one comment.
 * @param {{ id, resource_id, author, text, created_at }} comment
 * @returns {HTMLElement}
 */
function createCommentArticle(comment) {
  const article = document.createElement('article');

  const text = document.createElement('p');
  text.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = `Posted by: ${comment.author}`;

  article.appendChild(text);
  article.appendChild(footer);

  return article;
}

/**
 * Clears the comment list and re-renders every comment in currentComments.
 */
function renderComments() {
  commentListEl.innerHTML = '';

  currentComments.forEach(comment => {
    commentListEl.appendChild(createCommentArticle(comment));
  });
}

/**
 * Handles the comment form's submit event.
 * POSTs the new comment to the API, then refreshes the list.
 * @param {SubmitEvent} event
 */
async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentEl.value.trim();
  if (!commentText) return;

  try {
    const response = await fetch('./api/index.php?action=comment', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        resource_id: currentResourceId,
        author:      'Student',
        text:        commentText
      })
    });

    const result = await response.json();

    if (result.success && result.data) {
      currentComments.push(result.data);
      renderComments();
      newCommentEl.value = '';
    }
  } catch (error) {
    console.error('Failed to post comment:', error);
  }
}

/**
 * Entry point — fetches the resource and its comments in parallel,
 * then wires everything up.
 */
async function initializePage() {
  currentResourceId = getResourceIdFromURL();

  if (!currentResourceId) {
    titleEl.textContent = 'Resource not found.';
    return;
  }

  try {
    const [resourceRes, commentsRes] = await Promise.all([
      fetch(`./api/index.php?id=${currentResourceId}`),
      fetch(`./api/index.php?resource_id=${currentResourceId}&action=comments`)
    ]);

    const resourceJson = await resourceRes.json();
    const commentsJson = await commentsRes.json();

    // Store comments (fall back to empty array if none exist)
    currentComments = commentsJson.success && Array.isArray(commentsJson.data)
      ? commentsJson.data
      : [];

    if (resourceJson.success && resourceJson.data) {
      renderResourceDetails(resourceJson.data);
      renderComments();
      commentFormEl.addEventListener('submit', handleAddComment);
    } else {
      titleEl.textContent = 'Resource not found.';
    }

  } catch (error) {
    console.error('Failed to initialise page:', error);
    titleEl.textContent = 'Error loading resource.';
  }
}

// --- Initial Page Load ---
initializePage();
