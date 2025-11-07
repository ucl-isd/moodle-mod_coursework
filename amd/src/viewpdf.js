// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript module for handling personal extension modal.
 *
 * @module      mod_coursework/viewpdf
 * @copyright   2025 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {prefetchStrings} from 'core/prefetch';
import {getString} from 'core/str';
import {add as addToast} from 'core/toast';

var controller = {
    saveannotations: async function() {
        const pdfdata = await window.pdfcontroller.getpdfdata();
        const blob = new Blob([pdfdata], {type: "application/pdf"});
        const data = new FormData();
        data.append("annotations", blob, "annotated.pdf");
        data.append('submissionid', controller.currentfiledataset.submissionid);
        data.append('fileid', controller.currentfiledataset.fileid);
        data.append('filename', controller.currentfiledataset.filename);

        const response = await fetch("../../actions/uploadannotatedsubmissionajax.php", {
            method: "POST",
            body: data,
        });
        const result = await response.json();

        controller.currentfiledataset.annotatedfileurl = result.url;
        controller.currentfiledataset.annotatedfileid = result.fileid;

        addToast(getString('annotationssaved', 'mod_coursework'), {type: 'success'});
    },

    loadpdf: async function(dataset) {
        var url = dataset.href;

        if (dataset.annotatedfileurl) {
            url = dataset.annotatedfileurl;
        }
        controller.currentfiledataset = dataset;

        return window.pdfcontroller.loadpdf(url);

    },

    clearannotations: async function() {
        Ajax.call([{
            methodname: 'mod_coursework_clearannotations',
            args: {
                submissionid: controller.currentfiledataset.submissionid,
                fileid: controller.currentfiledataset.annotatedfileid
            },
        }])[0]
            .then(() => {
                controller.currentfiledataset.annotatedfileurl = '';
                controller.currentfiledataset.annotatedfileid = '';
                controller.loadpdf(controller.currentfiledataset);
                addToast(getString('annotationscleared', 'mod_coursework'), {type: 'success'});
                return;
            })
            .catch((error) => {
                addToast(error, {type: 'error'});
            });
    },

};


export const init = async() => {
    prefetchStrings('mod_coursework', [
        'annotationssaved',
        'annotationscleared',
    ]);

    let viewfilebuttons = document.querySelectorAll('[data-action="modcoursework_viewfile"]');
    viewfilebuttons.forEach((node) => {
            node.addEventListener("click", async(event) => {
                await controller.loadpdf(event.target.dataset).promise;
            });
        }
    );
    if (viewfilebuttons[0]) {
        await controller.loadpdf(viewfilebuttons[0].dataset).promise;
    }

    document.querySelector('[data-action="modcoursework_saveannotations"]')
        .addEventListener("click", async() => {
            await controller.saveannotations().promise;
        });

    document.querySelector('[data-action="modcoursework_clearannotations"]')
        .addEventListener("click", async() => {
            await controller.clearannotations().promise;
        });

};
