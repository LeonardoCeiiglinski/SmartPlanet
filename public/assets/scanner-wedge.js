(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  root.SmartPlanetWedge = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  var RESET_MS = 120;
  var LOOSE_MS = 90;
  var MAX = 64;

  function create() {
    var chars = [];

    function reset() {
      chars = [];
    }

    function push(key, now, repeat) {
      if (repeat) return { action: 'ignore' };
      if (key === 'Enter' || key === 'Tab') {
        var code = chars.map(function (c) { return c.key; }).join('');
        var maxGap = 0;
        var i;
        for (i = 1; i < chars.length; i++) maxGap = Math.max(maxGap, chars[i].t - chars[i - 1].t);
        var age = chars.length ? now - chars[chars.length - 1].t : 9999;
        reset();
        var curto = code.length >= 4 && code.length < 8 && maxGap <= 50 && age <= 200;
        var longo = code.length >= 8 && maxGap <= LOOSE_MS && age <= 300;
        if ((!curto && !longo) || !/^[\x20-\x7E]+$/.test(code)) return { action: 'ignore' };
        return { action: 'scan', code: code };
      }
      if (typeof key !== 'string' || key.length !== 1) return { action: 'ignore' };
      if (chars.length && now - chars[chars.length - 1].t > RESET_MS) reset();
      var gap = chars.length ? now - chars[chars.length - 1].t : 0;
      chars.push({ key: key, t: now });
      if (chars.length > MAX) chars = chars.slice(-MAX);
      var swallow = chars.length >= 8 && gap <= LOOSE_MS;
      return { action: swallow ? 'swallow' : 'buffer', index: chars.length };
    }

    return { push: push, reset: reset };
  }

  return { create: create };
});
