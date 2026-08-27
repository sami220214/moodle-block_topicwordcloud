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
 * Browser-side behaviour for the Topic word cloud block.
 *
 * @module     block_topicwordcloud/cloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define("block_topicwordcloud/cloud", ["core/ajax", "core/notification", "core/templates"], function(Ajax, Notification, Templates) {
    "use strict";

    const methodNames = {
        refresh: "block_topicwordcloud_get_state",
        submit: "block_topicwordcloud_submit_words",
        reset: "block_topicwordcloud_reset_cloud",
        deleteword: "block_topicwordcloud_delete_word",
        approveword: "block_topicwordcloud_approve_word"
    };

    const callService = (params, action, extra = {}) => {
        return Ajax.call([{
            methodname: methodNames[action],
            args: {
                blockinstanceid: params.blockinstanceid,
                ...extra
            }
        }])[0];
    };

    const replaceTemplate = async(target, templateName, context) => {
        const {html, js} = await Templates.renderForPromise(templateName, context);
        Templates.replaceNodeContents(target, html, js);
    };

    const confirmAction = (message) => new Promise((resolve) => {
        Notification.confirm("", message, resolve, () => resolve(false));
    });

    const getConfig = (root) => {
        const config = root.querySelector('[data-region="config"]');
        if (!config || !config.textContent.trim()) {
            return {};
        }

        return JSON.parse(config.textContent);
    };

    const buildTableContext = (rows, showUsers, strings, canManage, includeApprove) => {
        return {
            hasrows: rows.length > 0,
            rows: rows.map((row) => ({
                ...row,
                userdisplay: showUsers ? (row.usernames || []).join(", ") : row.usercount,
                showactions: canManage,
                showapprove: includeApprove,
                approveword: strings.approveword,
                deleteword: strings.deleteword
            })),
            emptytext: includeApprove ? strings.emptypending : strings.emptyanalytics,
            wordcolumn: strings.wordcolumn,
            countcolumn: strings.countcolumn,
            userscolumn: strings.userscolumn,
            showactions: canManage,
            actioncolumn: strings.actioncolumn
        };
    };

    const renderMeta = (root, state, strings) => {
        return replaceTemplate(root.querySelector('[data-region="meta"]'), "block_topicwordcloud/meta", {
            responseslabel: strings.responses,
            responses: state.totals.responses,
            responderslabel: strings.responders,
            responders: state.totals.responders,
            uniquewordslabel: strings.uniquewords,
            uniquewords: state.totals.uniquewords,
            showpending: state.moderationrequired,
            pendinglabel: strings.pendingcount,
            pending: state.totals.pending,
            remainingwordslabel: strings.remainingwords,
            remainingwords: state.remainingwords
        });
    };

    const renderCloud = (root, state, strings) => {
        return replaceTemplate(root.querySelector('[data-region="cloud"]'), "block_topicwordcloud/cloud", {
            haswords: state.cloudwords.length > 0,
            words: state.cloudwords,
            emptycloud: strings.emptycloud
        });
    };

    const renderAnalytics = (root, state, strings) => {
        const target = root.querySelector('[data-region="analytics"]');
        if (!state.canviewanalytics) {
            target.textContent = "";
            return Promise.resolve();
        }

        return replaceTemplate(target, "block_topicwordcloud/analytics", {
            heading: strings.analyticsheading,
            ...buildTableContext(state.analytics, state.showusernames, strings, state.canmanage, false)
        });
    };

    const renderManage = (root, state, strings) => {
        const target = root.querySelector('[data-region="manage"]');
        if (!state.canmanage) {
            target.textContent = "";
            return Promise.resolve();
        }

        return replaceTemplate(target, "block_topicwordcloud/manage", {
            heading: strings.manageheading,
            resetcloud: strings.resetcloud,
            showpendingtable: state.moderationrequired,
            pendingheading: strings.pendingheading,
            pendingtable: buildTableContext(state.pendingwords, state.showusernames, strings, true, true)
        });
    };

    const renderStatus = (root, message) => {
        root.querySelector('[data-region="status"]').textContent = message || "";
    };

    const renderFormState = (root, state) => {
        const form = root.querySelector('[data-region="form"]');
        const input = root.querySelector('[data-region="input"]');
        const button = form.querySelector('button[type="submit"]');
        input.disabled = !state.acceptingresponses;
        button.disabled = !state.acceptingresponses;
    };

    const render = async(root, params, state, message = "") => {
        renderStatus(root, message || state.statusmessage);
        renderFormState(root, state);
        await Promise.all([
            renderMeta(root, state, params.strings),
            renderCloud(root, state, params.strings),
            renderAnalytics(root, state, params.strings),
            renderManage(root, state, params.strings)
        ]);
    };

    const refresh = async(root, params, message = "") => {
        const json = await callService(params, "refresh");
        await render(root, params, json.state, message);
    };

    const runAction = async(root, params, action, word = "") => {
        if (action === "reset" && !await confirmAction(params.strings.confirmreset)) {
            return;
        }
        if (action === "deleteword" && !await confirmAction(params.strings.confirmdeleteword)) {
            return;
        }
        if (action === "approveword" && !await confirmAction(params.strings.confirmapproveword)) {
            return;
        }

        const extra = word ? {word: word} : {};
        const json = await callService(params, action, extra);
        await render(root, params, json.state, json.message || json.state.statusmessage);
    };

    return {
        init: function(initParams) {
            const params = typeof initParams === "string" ? {rootid: initParams} : {...initParams};
            const root = document.getElementById(params.rootid);
            if (!root) {
                return;
            }

            Object.assign(params, getConfig(root));
            params.strings = params.strings || {};
            params.blockinstanceid = root.dataset.blockinstanceid;
            renderStatus(root, params.strings.loading);

            refresh(root, params).catch((error) => {
                renderStatus(root, error.message);
            });

            root.querySelector('[data-region="form"]').addEventListener("submit", async(event) => {
                event.preventDefault();
                const input = root.querySelector('[data-region="input"]');
                try {
                    const json = await callService(params, "submit", {text: input.value});
                    input.value = "";
                    await render(root, params, json.state, json.message || json.state.statusmessage);
                } catch (error) {
                    renderStatus(root, error.message);
                }
            });

            root.addEventListener("click", (event) => {
                const button = event.target.closest("[data-action]");
                if (!button) {
                    return;
                }
                runAction(root, params, button.dataset.action, button.dataset.word).catch((error) => {
                    renderStatus(root, error.message);
                });
            });

            window.setInterval(() => {
                refresh(root, params).catch(() => {
                    return;
                });
            }, params.pollinterval || 15000);
        }
    };
});