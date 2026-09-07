(() => {
    const wpReady = () =>
        typeof window !== 'undefined' &&
        window.wp &&
        wp.data &&
        wp.data.select &&
        wp.data.dispatch

    const waitForWP = (fn) => {
        if (wpReady()) return fn()
        const id = setInterval(() => {
            if (wpReady()) {
                clearInterval(id)
                fn()
            }
        }, 120)
    }

    if (typeof window.__gbUidLogs === 'undefined') window.__gbUidLogs = false
    const log = (...a) => window.__gbUidLogs && console.log('[NB-UID]', ...a)
    const info = (...a) => window.__gbUidLogs && console.info('[NB-UID]', ...a)
    const group = (l) => window.__gbUidLogs && console.group(l)
    const groupEnd = () => window.__gbUidLogs && console.groupEnd()

    const isNectarBlock = (name = '') =>
        name.startsWith('nectar-blocks/') || name.startsWith('wp:nectar-blocks')

    const token = () =>
        Math.random().toString(36).slice(2, 8) + Date.now().toString(36).slice(-6)

    const nextBlockId = () => `block-${token()}`

    const flatten = (blocks) => {
        const out = []
        const walk = (bs) => {
            ;(bs || []).forEach((b) => {
                out.push(b)
                if (b.innerBlocks?.length) walk(b.innerBlocks)
            })
        }
        walk(blocks || [])
        return out
    }

    const attrVal = (block, key) => {
        const val = block?.attributes?.[key]
        return val == null || val === '' ? '' : String(val)
    }

    waitForWP(() => {
        const { select, subscribe, dispatch } = wp.data
        const be = 'core/block-editor'

        info('Initialized — stable blockIds; skip editor hydration; no divergent id attrs')

        let known = new Map()
        let hydrated = false

        let lastEvent = null
        let lastEventAt = 0
        const tag = (label) => {
            lastEvent = label
            lastEventAt = Date.now()
            log('event:', label)
            setTimeout(() => {
                if (Date.now() - lastEventAt >= 400) lastEvent = null
            }, 500)
        }
        document.addEventListener('paste', () => tag('paste'), true)
        const actions = wp.data.dispatch(be)
        ;['duplicateBlocks', 'insertBlocks', 'replaceBlocks'].forEach((fn) => {
            if (actions && typeof actions[fn] === 'function') {
                const orig = actions[fn]
                actions[fn] = (...args) => {
                    tag(fn === 'duplicateBlocks' ? 'duplicate' : 'insert')
                    return orig(...args)
                }
            }
        })

        let busy = false

        const all = () => flatten(select(be).getBlocks())

        //true if another nectar block already uses this canonical id
        const idUsedElsewhere = (blocks, clientId, canonical) => {
            if (!canonical) return false
            return blocks.some((other) => {
                if (other.clientId === clientId || !isNectarBlock(other.name)) return false
                const otherCanonical = attrVal(other, 'blockId') || attrVal(other, 'id')
                return otherCanonical === canonical
            })
        }

        subscribe(() => {
            if (busy) return

            const now = all()

            //first time blocks appear = editor hydration, not user insert — seed known and skip
            if (!hydrated) {
                if (!now.length) return
                known = new Map(now.map((b) => [b.clientId, 1]))
                hydrated = true
                info('Hydration complete — seeded', now.length, 'blocks without mutating IDs')
                return
            }

            const cause = lastEvent || 'add'
            const added = now.filter((b) => !known.has(b.clientId))
            if (!added.length) {
                known = new Map(now.map((b) => [b.clientId, 1]))
                return
            }

            const nectarAdded = added.filter((b) => isNectarBlock(b.name))
            if (!nectarAdded.length) {
                known = new Map(now.map((b) => [b.clientId, 1]))
                return
            }

            try {
                busy = true
                group(`[NB-UID] ${cause}: ${nectarAdded.length} Nectar block(s) detected`)

                const changes = []

                nectarAdded.forEach((b) => {
                    const blockId = attrVal(b, 'blockId')
                    const id = attrVal(b, 'id')
                    const canonical = blockId || id
                    const updates = {}

                    if (!canonical) {
                        //brand new block with no ids — generate one shared value
                        const next = nextBlockId()
                        updates.blockId = next
                        updates.id = next
                        changes.push({
                            cause,
                            block: b.name,
                            clientId: b.clientId,
                            reason: 'empty',
                            to: next,
                        })
                        log(`${b.name} (${b.clientId}) empty → "${next}"`)
                    } else if (idUsedElsewhere(now, b.clientId, canonical)) {
                        //duplicate of an existing id — regenerate shared value
                        const next = nextBlockId()
                        updates.blockId = next
                        updates.id = next
                        b.attributes = { ...(b.attributes || {}), blockId: next, id: next }
                        changes.push({
                            cause,
                            block: b.name,
                            clientId: b.clientId,
                            reason: 'duplicate',
                            from: canonical,
                            to: next,
                        })
                        log(`${b.name} (${b.clientId}) duplicate "${canonical}" → "${next}"`)
                    }
                    //if blockId exists and id is missing: do nothing
                    //filling id would dirty the block and trigger "Attempt recovery"

                    if (Object.keys(updates).length) {
                        dispatch(be).updateBlockAttributes(b.clientId, updates)
                    }
                })

                if (changes.length && window.__gbUidLogs) {
                    info('Summary:', `${changes.length} change(s)`)
                    console.table(changes)
                }
                groupEnd()
            } finally {
                known = new Map(now.map((b) => [b.clientId, 1]))
                busy = false
            }
        })
    })
})()
