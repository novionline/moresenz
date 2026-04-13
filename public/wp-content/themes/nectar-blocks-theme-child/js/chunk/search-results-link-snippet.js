//make entire search result card act like the inner link
(() => {
  const isInteractive = (el) => {
    if (!el) return false
    return !!el.closest('a, button, input, select, textarea, label, summary, details')
  }

  const enhanceResult = (resultEl) => {
    if (!resultEl || resultEl.dataset.linkSnippetApplied === '1') return

    const link =
      resultEl.querySelector('.title a[href]') ||
      resultEl.querySelector('a[href]')

    if (!link) return

    const href = link.getAttribute('href')
    if (!href) return

    resultEl.dataset.linkSnippetApplied = '1'
    resultEl.classList.add('js-link-snippet')
    resultEl.setAttribute('role', 'link')
    resultEl.setAttribute('tabindex', '0')

    const go = () => {
      const target = link.getAttribute('target')
      if (target === '_blank') {
        window.open(href, '_blank', 'noopener')
        return
      }
      window.location.href = href
    }

    resultEl.addEventListener('click', (e) => {
      if (isInteractive(e.target)) return
      go()
    })

    resultEl.addEventListener('keydown', (e) => {
      if (isInteractive(e.target)) return
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault()
        go()
      }
    })
  }

  const init = () => {
    document.querySelectorAll('#search-results article.result').forEach(enhanceResult)
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }
})()

