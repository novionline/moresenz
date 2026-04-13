const $accordionElements = Array.from(document.querySelectorAll('.novi-accordion'))
if ($accordionElements && $accordionElements.length > 0) {
    if (typeof window.noviAccordion === "undefined") {
        console.warn("Accordion JS class not found in initAccordion.js - Please make sure to include the Accordion JS class in your project.")
    } else {
        $accordionElements.forEach($accordionElement => new noviAccordion($accordionElement))
    }
}