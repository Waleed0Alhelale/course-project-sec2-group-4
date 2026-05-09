/*
  Requirement: Populate the "Course Resources" list page.
*/

// --- Element Selections ---
const resourceListSection = document.getElementById('resource-list-section');

// --- Functions ---

/**
 * Builds and returns a single <article> element for one resource.
 * @param {{ id, title, description, link }} resource
 * @returns {HTMLElement}
 */
function createResourceArticle(resource) {
  const article = document.createElement('article');

  const heading = document.createElement('h2');
  heading.textContent = resource.title;

  const description = document.createElement('p');
  description.textContent = resource.description;

  const anchor = document.createElement('a');
  anchor.href        = `details.html?id=${resource.id}`;
  anchor.textContent = 'View Resource & Discussion';

  article.appendChild(heading);
  article.appendChild(description);
  article.appendChild(anchor);

  return article;
}

/**
 * Fetches all resources from the API and renders them into the list section.
 */
async function loadResources() {
  try {
    const response = await fetch('./api/index.php');
    const result   = await response.json();

    // Clear any existing content
    resourceListSection.innerHTML = '';

    if (result.success && Array.isArray(result.data)) {
      result.data.forEach(resource => {
        resourceListSection.appendChild(createResourceArticle(resource));
      });
    } else {
      resourceListSection.innerHTML = '<p>No resources available at this time.</p>';
    }

  } catch (error) {
    console.error('Failed to load resources:', error);
    resourceListSection.innerHTML = '<p>Error loading resources. Please try again later.</p>';
  }
}

// --- Initial Page Load ---
loadResources();
