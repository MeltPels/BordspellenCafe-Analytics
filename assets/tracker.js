(function () {
  if (!window.BCA) return;

  const endpoint = window.BCA.endpoint;
  const nonce = window.BCA.nonce;
  const heartbeatSeconds = window.BCA.heartbeatSeconds || 15;

  function uuidv4() {
    // Goede-enough UUIDv4 zonder fingerprinting
    // (crypto.randomUUID is ideaal als beschikbaar)
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    const buf = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(buf);
    // RFC4122 variant
    buf[6] = (buf[6] & 0x0f) | 0x40;
    buf[8] = (buf[8] & 0x3f) | 0x80;
    const hex = [...buf].map(b => b.toString(16).padStart(2, "0")).join("");
    return `${hex.substr(0,8)}-${hex.substr(8,4)}-${hex.substr(12,4)}-${hex.substr(16,4)}-${hex.substr(20)}`;
  }

  const storageKey = "bca_session_id";
  let sessionId = sessionStorage.getItem(storageKey);
  if (!sessionId) {
    sessionId = uuidv4();
    sessionStorage.setItem(storageKey, sessionId);
  }

  function payload(eventType) {
    return {
      session_id: sessionId,
      event_type: eventType,
      url: window.location.href,
      referrer: document.referrer || ""
    };
  }

  async function post(eventType) {
    try {
      await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": nonce
        },
        body: JSON.stringify(payload(eventType)),
        keepalive: true
      });
    } catch (e) {
      // stil falen; analytics mag site niet breken
    }
  }

  // Initial page view
  post("page_view");

  // Heartbeat alleen als tab zichtbaar is
  let hb = setInterval(() => {
    if (document.visibilityState === "visible") {
      post("heartbeat");
    }
  }, heartbeatSeconds * 1000);

  // Exit: best-effort sendBeacon
  function sendExit() {
    try {
      const data = JSON.stringify(payload("page_exit"));
      if (navigator.sendBeacon) {
        const blob = new Blob([data], { type: "application/json" });
        navigator.sendBeacon(endpoint, blob);
      } else {
        fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-WP-Nonce": nonce },
          body: data,
          keepalive: true
        });
      }
    } catch (e) {}
  }

  window.addEventListener("pagehide", sendExit);
  window.addEventListener("beforeunload", sendExit);

})();
