// attacker_gen.js
// Lightweight attacker guess helper for the NetAware brute-force demo.
// Exposes: window.generateGuessesBatch(count, submittedPw, phaseMode)
//          window.resetSeenGuesses()

(function(global){
  // small weak/common password list (kept in generator for convenience)
  const weakPasswords = [
    "123456","password","123456789","12345","12345678",
    "qwerty","111111","123123","abc123","password1",
    "1234567","dragon","letmein","monkey","football",
    "iloveyou","admin","welcome","sunshine","princess",
    "cisco123","hello123","pass123","user123"
  ];

  const commonSuffixes = ['1','123','1234','2020','2021','2022','2023','2024','!','!@#'];

  function leetVariants(token) {
    const map = { a:'4', A:'4', o:'0', O:'0', e:'3', E:'3', i:'1', I:'1', s:'5', S:'5', t:'7', T:'7' };
    const results = new Set([token]);
    const chars = token.split('');
    for (let i=0;i<chars.length;i++){
      const c = chars[i];
      if (map[c]) {
        const arr = chars.slice();
        arr[i] = map[c];
        results.add(arr.join(''));
      }
    }
    results.add(token.toLowerCase());
    results.add(token.toUpperCase());
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
    return Array.from(new Set([...candidates, ...extras])).filter(Boolean);
  }

  function genTokenVariations(tokens) {
    const out = new Set();
    for (const t of tokens) {
      out.add(t);
      for (const s of commonSuffixes) out.add(t + s);
      for (const v of leetVariants(t)) out.add(v);
      out.add('!' + t);
      out.add(t + '!');
      out.add(t + '@123');
    }
    return Array.from(out);
  }

  // keep track of seen guesses during a simulation run (avoids repeats)
  let seenGuesses = new Set();
  function resetSeenGuesses() { seenGuesses = new Set(); }

  // generate prioritized guesses up to `count`
    function generateGuessesBatch(count, submittedPw = '', phaseMode = 'weak') {
    const batch = [];

    // 1) Always try weak/common passwords first
    for (const w of weakPasswords) {
        if (batch.length >= count) break;
        if (seenGuesses.has(w)) continue;
        batch.push(w); seenGuesses.add(w);
    }
    if (batch.length >= count) return batch;

    // 2) Only use token variations in WEAK phase
    if (phaseMode === 'weak') {
        const tokens = extractTokens(submittedPw);
        const tokenVars = genTokenVariations(tokens);
        for (const v of tokenVars) {
        if (batch.length >= count) break;
        if (!seenGuesses.has(v)) { batch.push(v); seenGuesses.add(v); }
        }
        if (batch.length >= count) return batch;

        // 3) More token + suffix combos (still weak phase only)
        for (const t of tokens) {
        for (const s of commonSuffixes) {
            if (batch.length >= count) break;
            const g = t + s;
            if (!seenGuesses.has(g)) { batch.push(g); seenGuesses.add(g); }
        }
        if (batch.length >= count) break;
        }
        if (batch.length >= count) return batch;
    }

    // 4) Leet variants of weak passwords
    for (const w of weakPasswords) {
        if (batch.length >= count) break;
        for (const v of leetVariants(w)) {
        if (batch.length >= count) break;
        if (!seenGuesses.has(v)) { batch.push(v); seenGuesses.add(v); }
        }
    }
    if (batch.length >= count) return batch;

    // 5) Fallback brute force (harder passwords require this)
    const fallbackChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    while (batch.length < count) {
        const len = (phaseMode === 'weak') ? 6 + Math.floor(Math.random()*3) : 10 + Math.floor(Math.random()*8);
        let s = '';
        for (let i=0;i<len;i++) s += fallbackChars[Math.floor(Math.random()*fallbackChars.length)];
        if (!seenGuesses.has(s)) { batch.push(s); seenGuesses.add(s); }
    }
    return batch;
    }
    function estimateEntropyCrackTime(password) {
    let pool = 0;
    if (/[a-z]/.test(password)) pool += 26;
    if (/[A-Z]/.test(password)) pool += 26;
    if (/[0-9]/.test(password)) pool += 10;
    if (/[^a-zA-Z0-9]/.test(password)) pool += 33;

    if (pool === 0) return { entropy: 0, timeYears: 0 };

    const length = password.length;
    const totalCombos = BigInt(pool) ** BigInt(length);
    
    // guesses per second
    const gps = 100_000_000_000n; // 1e11 guesses/sec
    const halfCombos = totalCombos / 2n;

    const seconds = halfCombos / gps;
    const years = Number(seconds) / (60 * 60 * 24 * 365);

    // entropy in bits
    const entropyBits = length * Math.log2(pool);

    return { entropy: entropyBits.toFixed(2), timeYears: years };
    }

  // expose to global
    global.attackerGen = {
    generateBatch: generateGuessesBatch,
    resetSeen: resetSeenGuesses,
    weakPasswords
    };
  // also export weak list if you want to reuse it
  global.attackerGen_weakPasswords = weakPasswords;
  global.estimateEntropyCrackTime = estimateEntropyCrackTime;

})(window);
