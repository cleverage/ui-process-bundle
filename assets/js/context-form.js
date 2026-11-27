// The context of a process schedule depends on its process: htmx swaps the row of the "context" field when the process
// changes (see ProcessScheduleCrudController). The collection of the free key/value pairs is bound again by EasyAdmin.
(function () {
    var processSelector = 'select[name="ProcessSchedule[process]"]';
    var contextSelector = '#context-tab-content';
    var edited = false;
    var previousProcess = null;

    function rememberProcess() {
        var select = document.querySelector(processSelector);
        previousProcess = select ? select.value : null;
    }

    // The values entered in the context are replaced by the ones of the new process: ask before losing them
    document.addEventListener('input', function (event) {
        if (event.target instanceof Element && event.target.closest(contextSelector)) {
            edited = true;
        }
    });
    document.addEventListener('htmx:confirm', function (event) {
        var element = event.detail.elt;
        if (!edited || !(element instanceof Element) || !element.matches(processSelector)) {
            return;
        }
        event.preventDefault();
        if (window.confirm(element.dataset.contextConfirm || 'The values entered in the context will be replaced. Continue?')) {
            event.detail.issueRequest(true);
        } else if (null !== previousProcess) {
            element.value = previousProcess;
        }
    });
    document.addEventListener('htmx:afterSwap', function () {
        edited = false;
        rememberProcess();
        document.dispatchEvent(new CustomEvent('ea.collection.item-added'));
    });
    document.addEventListener('DOMContentLoaded', rememberProcess);
})();
