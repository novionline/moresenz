(() => {
    const wpReady = () =>
      typeof window !== 'undefined' &&
      window.wp && wp.data && wp.data.select && wp.data.dispatch
  
    const waitForWP = (fn) => {
      if (wpReady()) return fn()
      const id = setInterval(() => {
        if (wpReady()) {
          clearInterval(id)
          fn()
        }
      }, 120)
    }
  
    // === CONFIG ===
    const TARGET_ATTRS = ['blockId', 'id']       // only these, nothing else
    const GENERATE_IF_EMPTY = true               // also create if the attr is missing/empty
    if (typeof window.__gbUidLogs === 'undefined') window.__gbUidLogs = false
    const log = (...a) => window.__gbUidLogs && console.log('[GB-UID]', ...a)
    const info = (...a) => window.__gbUidLogs && console.info('[GB-UID]', ...a)
    const warn = (...a) => window.__gbUidLogs && console.warn('[GB-UID]', ...a)
    const group = (l) => window.__gbUidLogs && console.group(l)
    const groupEnd = () => window.__gbUidLogs && console.groupEnd()
  
    const isNectarBlock = (name = '') =>
      name.startsWith('nectar-blocks/') || name.startsWith('wp:nectar-blocks')
  
    // token ~12 chars, alnum
    const token = () =>
      (Math.random().toString(36).slice(2, 8) + Date.now().toString(36).slice(-6))
  
    // Keep Nectar’s convention: prefer "block-" prefix when present
    const nextIdFor = (key, prev) => {
      const s = String(prev || '')
      const prefix =
        s.startsWith('block-') ? 'block-' :
        key === 'blockId' ? 'block-' :
        key === 'id' ? 'block-' : ''
      return prefix ? `${prefix}${token()}` : `${key}-${token()}`
    }
  
    const flatten = (blocks) => {
      const out = []
      const walk = (bs) => {
        bs.forEach(b => {
          out.push(b)
          if (b.innerBlocks?.length) walk(b.innerBlocks)
        })
      }
      walk(blocks || [])
      return out
    }
  
    waitForWP(() => {
      const { select, subscribe, dispatch } = wp.data
      const be = 'core/block-editor'
  
      info('Initialized — Nectar-only, regenerating [blockId,id] on paste/duplicate/pattern insert')
  
      const all = () => flatten(select(be).getBlocks())
      let known = new Map(all().map(b => [b.clientId, 1]))
  
      // Best-effort event tagging (for logs)
      let lastEvent = null
      let lastEventAt = 0
      const tag = (label) => {
        lastEvent = label
        lastEventAt = Date.now()
        log('event:', label)
        setTimeout(() => { if (Date.now() - lastEventAt >= 400) lastEvent = null }, 500)
      }
      document.addEventListener('paste', () => tag('paste'), true)
      const actions = wp.data.dispatch(be)
      ;['duplicateBlocks', 'insertBlocks', 'replaceBlocks'].forEach(fn => {
        if (actions && typeof actions[fn] === 'function') {
          const orig = actions[fn]
          actions[fn] = (...args) => {
            tag(fn === 'duplicateBlocks' ? 'duplicate' : 'insert')
            return orig(...args)
          }
        }
      })
  
      let busy = false
  
      subscribe(() => {
        if (busy) return
  
        const cause = lastEvent || 'add'
        const now = all()
        const added = now.filter(b => !known.has(b.clientId))
        if (!added.length) { known = new Map(now.map(b => [b.clientId, 1])); return }
  
        // Only Nectar blocks
        const nectarAdded = added.filter(b => isNectarBlock(b.name))
        if (!nectarAdded.length) { known = new Map(now.map(b => [b.clientId, 1])); return }
  
        try {
          busy = true
          group(`[GB-UID] ${cause}: ${nectarAdded.length} Nectar block(s) detected`)
          if (window.__gbUidLogs) {
            console.table(nectarAdded.map(b => ({
              block: b.name,
              clientId: b.clientId,
              has_blockId: !!(b.attributes && 'blockId' in b.attributes),
              has_id: !!(b.attributes && 'id' in b.attributes)
            })))
          }
  
          const changes = []
          nectarAdded.forEach(b => {
            const attrs = b.attributes || {}
            const updates = {}
  
            TARGET_ATTRS.forEach(key => {
              const hasAttr = Object.prototype.hasOwnProperty.call(attrs, key)
              const hasVal = hasAttr && attrs[key] != null && attrs[key] !== ''
  
              if (hasVal || (GENERATE_IF_EMPTY && (!hasAttr || !attrs[key]))) {
                const prev = hasVal ? String(attrs[key]) : ''
                const next = nextIdFor(key, prev)
                updates[key] = next
                changes.push({
                  cause,
                  block: b.name,
                  clientId: b.clientId,
                  attr: key,
                  from: prev,
                  to: next,
                  created: !hasVal
                })
                log(`${b.name} (${b.clientId}) ${key}: "${prev}" → "${next}"`)
              }
            })
  
            if (Object.keys(updates).length) {
              dispatch(be).updateBlockAttributes(b.clientId, updates)
            } else {
              warn('No target attrs to update on', b.name, `(${b.clientId})`)
            }
          })
  
          if (changes.length && window.__gbUidLogs) {
            info('Summary:', `${changes.length} attribute change(s)`)
            console.table(changes.map(c => ({
              cause: c.cause,
              block: c.block,
              clientId: c.clientId,
              attr: c.attr,
              from: c.from,
              to: c.to,
              created: c.created
            })))
          }
          groupEnd()
        } finally {
          known = new Map(now.map(b => [b.clientId, 1]))
          busy = false
        }
      })
    })
  })()
  