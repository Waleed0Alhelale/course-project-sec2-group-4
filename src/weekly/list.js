/*
  Requirement: Populate the "Weekly Course Breakdown" list page.

  Instructions:
  1. This file is already linked to `list.html` via:
         <script src="list.js" defer></script>

  2. In `list.html`, the <section id="week-list-section"> is the container
     that this script populates.

  3. Implement the TODOs below.
*/

// --- Element Selections ---
const weekListSection = document.getElementById("week-list-section");

// --- Functions ---

/**
 * Implement createWeekArticle.
 *
 * Parameters:
 *   week — one object from the API response with the shape:
 *     {
 *       id:          number,   // integer primary key from the weeks table
 *       title:       string,
 *       start_date:  string,   // "YYYY-MM-DD" — matches the SQL column name
 *       description: string,
 *       links:       string[]  // already decoded array of URL strings
 *     }
 *
 * Returns:
 *   An <article> element matching the structure shown in list.html:
 *     <article>
 *       <h2>{title}</h2>
 *       <p>Starts on: {start_date}</p>
 *       <p>{description}</p>
 *       <a href="details.html?id={id}">View Details & Discussion</a>
 *     </article>
 *
 * Important: the href MUST be "details.html?id=<id>" (integer id from
 * the weeks table) so that details.js can read the id from the URL.
 */
function createWeekArticle(week) {
  const article = document.createElement("article");
  article.className = "card";

  const heading = document.createElement("h2");
  heading.textContent = week.title || "";

  const startDatePara = document.createElement("p");
  startDatePara.textContent = "Starts on: " + (week.start_date || "");

  const descriptionPara = document.createElement("p");
  descriptionPara.textContent = week.description || "";

  const link = document.createElement("a");
  link.href = "details.html?id=" + week.id;
  link.textContent = "View Details & Discussion";

  article.append(heading, startDatePara, descriptionPara, link);
  return article;
}

/**
 * Implement loadWeeks (async).
 *
 * It should:
 * 1. Use fetch() to GET data from './api/index.php'.
 *    The API returns JSON in the shape:
 *      { success: true, data: [ ...week objects ] }
 * 2. Parse the JSON response.
 * 3. Clear any existing content from the list section.
 * 4. Loop through the data array. For each week object:
 *    - Call createWeekArticle(week).
 *    - Append the returned <article> to the list section.
 */
async function loadWeeks() {
  try {
    const response = await fetch("./api/index.php");
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load weeks.");
    }

    weekListSection.innerHTML = "";

    const weeks = Array.isArray(result.data) ? result.data : [];
    weeks.forEach(function (week) {
      weekListSection.appendChild(createWeekArticle(week));
    });
  } catch (error) {
    weekListSection.innerHTML = "";

    const errorPara = document.createElement("p");
    errorPara.textContent = error.message || "Unable to load weeks.";
    weekListSection.appendChild(errorPara);
  }
}

// --- Initial Page Load ---
loadWeeks();
