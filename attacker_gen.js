(function (global) {
  // Lightweight attacker guess helper for the NetAware demo.
  // Exposes: window.attackerGen.generateBatch(count, submittedPw, phaseMode)
  //          window.attackerGen.resetSeen()
  //          window.attackerGen_weakPasswords
  //          window.estimateEntropyCrackTime
  const weakPasswords = [
    "123456", "123", "1234", "password", "123456789", "12345", "12345678",
    "qwerty", "111111", "123123", "abc123", "password1",
    "1234567", "dragon", "letmein", "monkey", "football",
    "iloveyou", "admin", "welcome", "sunshine", "princess",
    "cisco123", "hello123", "pass123", "user123",
    "admin123", "password123", "1234567890", "letmein123", "welcome1",
    "qwerty123", "passw0rd", "p@ssword", "iloveyou1", "admin2023",
    "guest", "guest123", "user", "user1234", "test", "test123",
    "asdfgh", "zxcvbn", "superman", "batman", "pokemon", "naruto",
    "dragon123", "love123", "football1", "baseball", "trustno1",
    "hello1234", "welcome123", "qwe123", "qweasd", "login123",
    "root", "root123", "default", "default123", "changeme"
  ];

  // small helpers
  function shuffleInPlace(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      const tmp = arr[i];
      arr[i] = arr[j];
      arr[j] = tmp;
    }
    return arr;
  }

  function shuffle(arr) {
    return shuffleInPlace(arr.slice());
  }

  function randFloat() {
    return Math.random();
  }

  const commonSuffixes = [
    '1', '01', '123', '1234', '12345', '321',
    '2020', '2021', '2022', '2023', '2024', '2025', '2026',
    '!', '!!', '!?', '?', '@', '@1', '#', '###',
    '_123', '.123', '-1', '-123', '@2023', '@2024', '_2024', '!!1'
  ];

  function leetVariants(token) {
    const map = {
      a: '4', A: '4', o: '0', O: '0', e: '3', E: '3',
      i: '1', I: '1', s: '5', S: '5', t: '7', T: '7'
    };

    const results = new Set([token]);
    const chars = token.split('');

    for (let i = 0; i < chars.length; i++) {
      const c = chars[i];
      if (map[c]) {
        const arr = chars.slice();
        arr[i] = map[c];
        results.add(arr.join(''));
      }
    }

    results.add(token.toLowerCase());
    results.add(token.toUpperCase());

    const multi = chars.map(ch => map[ch] || ch).join('');
    results.add(multi);

    // add small appended numeric variants
    results.add(token + '1');
    results.add(token + '!');

    return Array.from(results);
  }

  function extractTokens(pw) {
    if (!pw) return [];

    const candidates = pw.split(/[^A-Za-z0-9]+/).filter(Boolean);
    const extras = [];

    for (const c of candidates) {
      const parts = c.match(/[A-Za-z]+|[0-9]+/g);
      if (parts) extras.push(...parts);
    }

    const unique = Array.from(new Set([...candidates, ...extras]));
    return unique.filter(t => t.length > 0 && !(t.length === 1 && /^[0-9]$/.test(t)));
  }

  function genTokenVariations(tokens) {
    const out = new Set();

    for (const t of tokens) {
      if (!t) continue;
      out.add(t);

      for (const s of commonSuffixes) out.add(t + s);
      for (const v of leetVariants(t)) out.add(v);

      out.add('!' + t);
      out.add(t + '!');
      out.add(t + '@123');

      if (/[A-Za-z]/.test(t)) {
        out.add(t + '-' + t);
        out.add(t + '_' + t);
      }
    }

    return shuffle(Array.from(out));
  }
  // seen guesses per simulation
  let seenGuesses = new Set();

  function resetSeenGuesses() {
    seenGuesses = new Set();
    // also reset generator run state
    initRunState();
  }

  // run-level caches and state
  let runState = null;

  function initRunState() {
    runState = {
      // per-bucket shuffled arrays consumed progressively
      buckets: {
        common: shuffle(weakPasswords.slice()),
        leetCommon: [],
        tokenVars: [],
        tokenSuffixes: [],
        brute: null
      },
      // initial weights. these will be decayed as buckets exhaust
      weights: {
        common: 0.35,
        tokenVars: 0.30,
        leetCommon: 0.15,
        tokenSuffixes: 0.10,
        brute: 0.10
      },
      picks: 0,
      // minimal guard so first few picks can avoid raw common to reduce predictability
      initialCommonDelay: 8
    };
  }

  initRunState();

  function ensureLeetCommon() {
    if (runState.buckets.leetCommon.length === 0) {
      const out = [];
      for (const w of weakPasswords) {
        const lv = leetVariants(w);
        for (const v of lv) out.push(v);
      }
      runState.buckets.leetCommon = shuffle(Array.from(new Set(out)));
    }
  }

  function ensureBrutePool() {
    if (runState.buckets.brute) return;

    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const pool = [];

    // generate a moderate sized pool rather than full combinatorial space
    for (let i = 0; i < 3000; i++) {
      const len = 6 + Math.floor(Math.random() * 6);
      let s = '';
      for (let j = 0; j < len; j++) s += chars[Math.floor(Math.random() * chars.length)];
      pool.push(s);
    }

    runState.buckets.brute = shuffle(Array.from(new Set(pool)));
  }

  // weighted bucket selection that adapts as buckets exhaust
  function pickBucket() {
    // Build active weights excluding empty buckets
    const active = [];
    let total = 0;

    for (const k of Object.keys(runState.weights)) {
      const arr = runState.buckets[k];
      const empty = !arr || arr.length === 0;

      // special rule: delay raw 'common' for first N picks to avoid immediate hits on obvious lists
      if (k === 'common' && runState.picks < runState.initialCommonDelay) {
        // treat as empty for selection
        continue;
      }

      if (!empty) {
        const w = runState.weights[k];
        active.push({ key: k, w });
        total += w;
      }
    }

    if (active.length === 0) return null;

    const r = Math.random() * total;
    let acc = 0;
    for (const item of active) {
      acc += item.w;
      if (r <= acc) return item.key;
    }

    return active[active.length - 1].key;
  }

  function decayWeight(bucketKey) {
    // reduce bucket weight moderately when used heavily
    runState.weights[bucketKey] = Math.max(0.01, runState.weights[bucketKey] * 0.88);
    // gently boost brute force weight a bit over time so the attack broadens
    runState.weights.brute = Math.min(0.45, runState.weights.brute + 0.005);

    // normalize weights so sum ~1
    const sum = Object.values(runState.weights).reduce((a, b) => a + b, 0);
    for (const k of Object.keys(runState.weights)) runState.weights[k] = runState.weights[k] / sum;
  }

  // generate a batch of guesses
  function generateGuessesBatch(count, submittedPw = '', phaseMode = 'weak') {
    if (!runState) initRunState();

    // on first call with a new submittedPw, refresh token-based buckets
    const tokens = extractTokens(submittedPw);
    if (tokens.length > 0) {
      runState.buckets.tokenVars = genTokenVariations(tokens);
      runState.buckets.tokenSuffixes = shuffle(tokens.flatMap(t => commonSuffixes.map(s => t + s)));
    } else {
      runState.buckets.tokenVars = [];
      runState.buckets.tokenSuffixes = [];
    }

    ensureLeetCommon();
    ensureBrutePool();

    const out = [];
    // adaptive per-batch size to ensure diversity
    const maxAttempts = Math.max(1, count);
    let tries = 0;

    while (out.length < maxAttempts && tries < maxAttempts * 8) {
      tries++;
      const bucket = pickBucket();
      if (!bucket) break;
      let cand = null;

      // Pull candidate from selected bucket
      const arr = runState.buckets[bucket];
      if (arr && arr.length > 0) {
        cand = arr.shift();
      } else {
        // fallback to brute pool
        if (runState.buckets.brute && runState.buckets.brute.length > 0) {
          cand = runState.buckets.brute.shift();
        } else {
          // create a synthetic fallback
          cand = Math.random().toString(36).slice(2, 10);
        }
      }

      // small post-processing based on phaseMode and randomness to reduce predictability
      if (bucket === 'common' || bucket === 'leetCommon') {
        // occasionally append a common suffix to make variants
        if (Math.random() < 0.18) cand = cand + commonSuffixes[Math.floor(Math.random() * commonSuffixes.length)];
      }

      if (bucket === 'tokenVars' && Math.random() < 0.12) {
        // occasionally mix token with a weak password from list
        const w = weakPasswords[Math.floor(Math.random() * weakPasswords.length)];
        cand = cand + w.slice(0, 3);
      }

      // avoid duplicates in-run
      if (!cand) continue;
      if (seenGuesses.has(cand)) {
        // slight chance to accept duplicates to mimic retries, but generally skip
        if (Math.random() < 0.02) {
          // allow a rare retry
        } else continue;
      }

      // safety: do not output the raw submitted password as a guess in early picks unless it is trivially common
      if (runState.picks < 6 && submittedPw && cand === submittedPw && weakPasswords.indexOf(submittedPw) === -1) {
        // push the submitted password back to a low-priority place
        if (!runState.buckets.brute) ensureBrutePool();
        runState.buckets.brute.push(cand);
        continue;
      }

      // accept candidate
      out.push(cand);
      seenGuesses.add(cand);
      runState.picks++;
      decayWeight(bucket);
    }

    // If we still need more, fill with brute variants
    ensureBrutePool();
    while (out.length < maxAttempts && runState.buckets.brute.length > 0) {
      const c = runState.buckets.brute.shift();
      if (!seenGuesses.has(c)) {
        out.push(c);
        seenGuesses.add(c);
      }
    }

    // final fallback random strings
    const fallbackChars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    while (out.length < maxAttempts) {
      let s = '';
      const len = 6 + Math.floor(Math.random() * 6);
      for (let i = 0; i < len; i++) s += fallbackChars[Math.floor(Math.random() * fallbackChars.length)];
      if (!seenGuesses.has(s)) {
        out.push(s);
        seenGuesses.add(s);
      }
    }

    // Shuffle batch to avoid predictable ordering inside the batch
    return shuffle(out);
  }

function estimateEntropyCrackTime(password) {
  // Quick guard
  if (!password || password.length === 0) return { entropy: 0, timeSeconds: 0, timeYears: 0 };

  // Lowercase weak set for quick lookup
  const weakSet = new Set(weakPasswords.map(w => w.toLowerCase()));

  // Extract tokens already defined earlier in attacker_gen.js
  const tokens = extractTokens(password).map(t => String(t).toLowerCase());

  // Heuristic: if any token is a known weak password or the whole password is weak -> dictionary-style attack
  const tokensInWeak = tokens.filter(t => weakSet.has(t));
  const lowerPassword = password.toLowerCase().replace(/[^a-z0-9]/g, '');

  // If exact weak match, treat as instantly cracked (very small time)
  if (weakSet.has(password.toLowerCase()) || weakSet.has(lowerPassword)) {
    return { entropy: 0, timeSeconds: 0.5, timeYears: 0 };
  }

  // If password *contains* weak token(s) (e.g., "password12!", "letmein2023") treat as dictionary variant
  if (tokensInWeak.length > 0) {
    // Base dictionary guesses (approx size of common lists & variants)
    let baseGuesses = 50000; // 50k common entries
    // Increase guesses for multiple tokens or longer suffixes (more variants)
    baseGuesses *= (1 + Math.min(4, tokensInWeak.length)); // up to ~5x
    // Boost for numeric/symbol appendices
    if (/[0-9]/.test(password)) baseGuesses *= 6;   // account for many numeric suffix variants
    if (/[^a-zA-Z0-9]/.test(password)) baseGuesses *= 2; // punctuation variants
    // Factor for leet-like substitutions
    if (/[0-9]/.test(password) || /[4@3!1]/.test(password)) baseGuesses *= 1.5;

    // Attacker offline dictionary speed (fast): ~1e6 guesses/sec conservatively
    const dictSpeed = 1e6;
    const timeSeconds = Math.max(0.2, baseGuesses / dictSpeed);

    // Entropy estimate: simple bits from pool (kept for UI)
    let pool = 0;
    if (/[a-z]/.test(password)) pool += 26;
    if (/[A-Z]/.test(password)) pool += 26;
    if (/[0-9]/.test(password)) pool += 10;
    if (/[^a-zA-Z0-9]/.test(password)) pool += 33;
    const entropyBits = pool > 0 ? (password.length * Math.log2(pool)) : 0;

    return {
      entropy: Number(entropyBits.toFixed(2)),
      timeSeconds: timeSeconds,
      timeYears: timeSeconds / (60 * 60 * 24 * 365)
    };
  }

  // --- FALLBACK: entropy-based brute-force estimate (for random-like passwords) ---
  // Character pool estimate
  let pool = 0;
  if (/[a-z]/.test(password)) pool += 26;
  if (/[A-Z]/.test(password)) pool += 26;
  if (/[0-9]/.test(password)) pool += 10;
  if (/[^a-zA-Z0-9]/.test(password)) pool += 33;

  if (pool === 0) return { entropy: 0, timeSeconds: 0, timeYears: 0 };

  const length = password.length;
  const entropyBits = length * Math.log2(pool);
  const entropyRounded = Number(entropyBits.toFixed(2));

  // Choose an aggressive guesses/sec for offline attacks (adjustable)
  // 1e10 = 10 billion guesses/sec (represents a fast cluster / GPU farm)
  // Pick higher if you want shorter times (more pessimistic for defenders).
  const guessesPerSecond = 1e10;
  // Estimate time to try half the space: seconds ≈ 2^(entropyBits-1) / guessesPerSecond
  // Use logs to avoid overflow
  const log10Seconds = (entropyBits - 1) * Math.log10(2) - Math.log10(guessesPerSecond);

  let timeSeconds;
  if (!isFinite(log10Seconds) || log10Seconds > 308) {
    timeSeconds = Infinity;
  } else if (log10Seconds < -12) {
    timeSeconds = 0;
  } else {
    timeSeconds = Math.pow(10, log10Seconds);
  }
  const timeYears = (timeSeconds === Infinity) ? Infinity : timeSeconds / (60 * 60 * 24 * 365);
  return {
    entropy: entropyRounded,
    timeSeconds: timeSeconds,
    timeYears: timeYears
  };
}
  // preserve old API names
  global.attackerGen = {
    generateBatch: generateGuessesBatch,
    resetSeen: resetSeenGuesses,
    weakPasswords: weakPasswords
  };
  global.attackerGen_weakPasswords = weakPasswords;
  global.estimateEntropyCrackTime = estimateEntropyCrackTime;
  // initialize seen state for page load
  resetSeenGuesses();
})(window);