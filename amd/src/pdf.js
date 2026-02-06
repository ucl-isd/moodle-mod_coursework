/**
 * PDF viewer logic for mod_coursework.
 *
 * @module     mod_coursework/pdf
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Render a specific page of the PDF.
 *
 * @param {Object} pdf
 * @param {Number} pagenum
 * @param {HTMLCanvasElement} canvas
 * @param {Number} scalefactor
 */
const renderpage = (pdf, pagenum, canvas, scalefactor) => {
    pdf.getPage(pagenum).then((page) => {
        const viewport = page.getViewport({scale: scalefactor});
        const context = canvas.getContext('2d');

        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const rendercontext = {
            canvasContext: context,
            viewport: viewport
        };
        page.render(rendercontext);
    });
};

/**
 * Initialize the PDF viewer.
 *
 * @param {Object} pdfjsLib The library object imported in the template.
 * @param {String} pdfurl
 */
export const init = (pdfjsLib, pdfurl) => {
    const scalefactor = 1;
    const viewercontainer = document.getElementById('pdf-viewer');

    if (!viewercontainer || !pdfjsLib) {
        return;
    }

    pdfjsLib.getDocument(pdfurl).promise.then((pdfdoc) => {
        for (let pagenum = 1; pagenum <= pdfdoc.numPages; pagenum++) {
            const canvas = document.createElement('canvas');
            viewercontainer.appendChild(canvas);
            renderpage(pdfdoc, pagenum, canvas, scalefactor);
        }
    }).catch((error) => {
        // eslint-disable-next-line no-console
        console.error("Error loading PDF:", error);
    });
};