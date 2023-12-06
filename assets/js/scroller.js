const scroller = scrollama();

function handleStepEnter(response) {
    response.element.classList.add('is-active');
}

function handleStepExit(response) {
    response.element.classList.remove('is-active');
}

function init() {
    scroller
        .setup({
            step: '.scroll-section',
            offset: 0.5,
            debug: false,
        })
        .onStepEnter(handleStepEnter)
        .onStepExit(handleStepExit);
}

init();