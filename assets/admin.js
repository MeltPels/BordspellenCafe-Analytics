(function () {
  // Later bouwen we hier het dashboard (polling live + 30d stats).
  // Voor nu: laat zien dat endpoints werken.
  if (!window.BCA_ADMIN) return;

  const root = document.getElementById("bca-admin-root");
  if (!root) return;

  root.innerHTML = "<p>Endpoints testen...</p>";

  async function get(url) {
    const res = await fetch(url, { headers: { "X-WP-Nonce": window.BCA_ADMIN.nonce }});
    return res.json();
  }

  Promise.all([
    get(window.BCA_ADMIN.liveEndpoint),
    get(window.BCA_ADMIN.statsEndpoint)
  ]).then(([live, stats]) => {
    root.innerHTML =
      "<pre>" +
      "LIVE:\n" + JSON.stringify(live, null, 2) +
      "\n\nSTATS:\n" + JSON.stringify(stats, null, 2) +
      "</pre>";
  }).catch(() => {
    root.innerHTML = "<p>Kon endpoints niet laden.</p>";
  });

})();
