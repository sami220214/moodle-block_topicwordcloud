define("block_topicwordcloud/cloud", [], function() {
    "use strict";

    const post = async(params, action, extra = {}) => {
        const body = new URLSearchParams({
            sesskey: params.sesskey,
            action: action,
            blockinstanceid: params.blockinstanceid,
            ...extra
        });

        const response = await fetch(params.ajaxurl, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: body.toString()
        });

        const json = await response.json();
        if (!json.success) {
            throw new Error(json.error || "Request failed");
        }
        return json;
    };

    const escapeHtml = (value) => String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#039;");

    const renderMeta = (root, state, strings) => {
        const target = root.querySelector('[data-region="meta"]');
        target.innerHTML = `
            <div class="block-topicwordcloud__chips">
                <span class="block-topicwordcloud__chip"><strong>${escapeHtml(strings.responses)}:</strong> ${state.totals.responses}</span>
                <span class="block-topicwordcloud__chip"><strong>${escapeHtml(strings.responders)}:</strong> ${state.totals.responders}</span>
                <span class="block-topicwordcloud__chip"><strong>${escapeHtml(strings.uniquewords)}:</strong> ${state.totals.uniquewords}</span>
                ${state.moderationrequired ? `<span class="block-topicwordcloud__chip"><strong>${escapeHtml(strings.pendingcount)}:</strong> ${state.totals.pending}</span>` : ""}
                <span class="block-topicwordcloud__chip"><strong>${escapeHtml(strings.remainingwords)}:</strong> ${state.remainingwords}</span>
            </div>
        `;
    };

    const renderCloud = (root, state, strings) => {
        const target = root.querySelector('[data-region="cloud"]');
        if (!state.cloudwords.length) {
            target.innerHTML = `<p class="block-topicwordcloud__empty">${escapeHtml(strings.emptycloud)}</p>`;
            return;
        }

        target.innerHTML = `
            <div class="block-topicwordcloud__cloudinner">
                ${state.cloudwords.map((item) => `
                    <span class="block-topicwordcloud__word block-topicwordcloud__word--${item.colorindex}"
                        style="font-size:${item.size}px"
                        title="${escapeHtml(item.word)} (${item.count})">
                        ${escapeHtml(item.word)}
                    </span>
                `).join("")}
            </div>
        `;
    };

    const renderAnalyticsTable = (rows, showUsers, strings, canManage, includeApprove) => {
        if (!rows.length) {
            return `<p class="block-topicwordcloud__empty">${escapeHtml(includeApprove ? strings.emptypending : strings.emptyanalytics)}</p>`;
        }

        return `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>${escapeHtml(strings.wordcolumn)}</th>
                            <th>${escapeHtml(strings.countcolumn)}</th>
                            <th>${escapeHtml(strings.userscolumn)}</th>
                            ${canManage ? `<th>${escapeHtml(strings.actioncolumn)}</th>` : ""}
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => `
                            <tr>
                                <td>${escapeHtml(row.word)}</td>
                                <td>${row.count}</td>
                                <td>${showUsers ? escapeHtml((row.usernames || []).join(", ")) : row.usercount}</td>
                                ${canManage ? `<td class="block-topicwordcloud__actions">
                                    ${includeApprove ? `<button type="button" class="btn btn-sm btn-secondary" data-action="approveword" data-word="${escapeHtml(row.normalizedword)}">${escapeHtml(strings.approveword)}</button>` : ""}
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-action="deleteword" data-word="${escapeHtml(row.normalizedword)}">${escapeHtml(strings.deleteword)}</button>
                                </td>` : ""}
                            </tr>
                        `).join("")}
                    </tbody>
                </table>
            </div>
        `;
    };

    const renderAnalytics = (root, state, strings) => {
        const target = root.querySelector('[data-region="analytics"]');
        if (!state.canviewanalytics) {
            target.innerHTML = "";
            return;
        }

        target.innerHTML = `
            <h4 class="block-topicwordcloud__sectiontitle">${escapeHtml(strings.analyticsheading)}</h4>
            ${renderAnalyticsTable(state.analytics, state.showusernames, strings, state.canmanage, false)}
        `;
    };

    const renderManage = (root, state, strings) => {
        const target = root.querySelector('[data-region="manage"]');
        if (!state.canmanage) {
            target.innerHTML = "";
            return;
        }

        const pendingMarkup = state.moderationrequired ? `
            <h4 class="block-topicwordcloud__sectiontitle">${escapeHtml(strings.pendingheading)}</h4>
            ${renderAnalyticsTable(state.pendingwords, state.showusernames, strings, true, true)}
        ` : "";

        target.innerHTML = `
            <h4 class="block-topicwordcloud__sectiontitle">${escapeHtml(strings.manageheading)}</h4>
            <div class="block-topicwordcloud__toolbar">
                <button type="button" class="btn btn-outline-danger" data-action="reset">${escapeHtml(strings.resetcloud)}</button>
            </div>
            ${pendingMarkup}
        `;
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

    const render = (root, params, state, message = "") => {
        renderStatus(root, message || state.statusmessage);
        renderFormState(root, state);
        renderMeta(root, state, params.strings);
        renderCloud(root, state, params.strings);
        renderAnalytics(root, state, params.strings);
        renderManage(root, state, params.strings);
    };

    const refresh = async(root, params, message = "") => {
        const json = await post(params, "refresh");
        render(root, params, json.state, message);
    };

    const runAction = async(root, params, action, word = "") => {
        if (action === "reset" && !window.confirm(params.strings.confirmreset)) {
            return;
        }
        if (action === "deleteword" && !window.confirm(params.strings.confirmdeleteword)) {
            return;
        }
        if (action === "approveword" && !window.confirm(params.strings.confirmapproveword)) {
            return;
        }

        const extra = word ? {word: word} : {};
        const json = await post(params, action, extra);
        render(root, params, json.state, json.message || json.state.statusmessage);
    };

    return {
        init: function(params) {
            const root = document.getElementById(params.rootid);
            if (!root) {
                return;
            }

            params.blockinstanceid = root.dataset.blockinstanceid;
            renderStatus(root, params.strings.loading);

            refresh(root, params).catch((error) => {
                renderStatus(root, error.message);
            });

            root.querySelector('[data-region="form"]').addEventListener("submit", async(event) => {
                event.preventDefault();
                const input = root.querySelector('[data-region="input"]');
                try {
                    const json = await post(params, "submit", {text: input.value});
                    input.value = "";
                    render(root, params, json.state, json.message || json.state.statusmessage);
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
