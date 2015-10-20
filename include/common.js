// Constants
var rate = 5000; // Refresh rate in msec.
var halfRate = parseInt(rate / 2);
var flashDuration = 7500; // Flash for 7.5 seconds.  Set to 0 to disable.
var title = null;

// Globals
var allCallers = false;
var updateXML = null;
var colIdxId = 0;
var colIdxLine = 1;
var colIdxTime = 2;
var colIdxPriority = 3;
var colIdxOnline = 4;
var colIdxName = 5;
var colIdxTopic = 6;
var colNames = ["id", "line", "time", "priority", "online", "name", "topic"];
var debug = false;
var editMode = false;
var emptyCallerLen = 0;
var flashServerMsec = 0;
var lineToId = new Array();
var logStarted = false;
var modified = -1; // Server's MTIME for the entire database.
var nowClientMsec = 0;
var nowServerMsec = 0;
var offset = 0; // Client time - server time in msec.
var paused = false;
var pendingCount = 0;
var numCallers = 0; // Number of callers currently displayed.
var randomized = false;
var redirect = null;
var rtt = 0;
var startClientMsec = 0; // Start client time for RTT calculations.
var sendXML = null;
var startServerMsec = 0; // Start server time for RTT calculations.
var synced = false;
var updated = false; // If there are pending changes to send.
var updates = 0; // Number of updates made.
var valueLast = null;
var xh = null;

// Utility and conversion functions.

function simpleDate(dateObj)
{
    return ("" + (100 + dateObj.getHours())).substring(1) + ":" +
        ("" + (100 + dateObj.getMinutes())).substring(1) + ":" +
        ("" + (100 + dateObj.getSeconds())).substring(1);
}

function priToChar(pri)
{
    if (pri < 5)
    {
        return "H";
    }
    else if (pri == 5)
    {
        return "M";
    }
    else
    {
        return "L";
    }
}

function charToPri(char)
{
    if (char == "H")
    {
        return 4;
    }
    else if (char == "M")
    {
        return 5;
    }
    else
    {
        return 6;
    }
}

function priToClass(online, pri)
{
    if (online == 0)
    {
        return "old";
    }

    if (pri == "H")
    {
        return "high";
    }
    else if (pri == "M")
    {
        return "medium";
    }
    else
    {
        return "low";
    }
}

function numToDate(dateNum)
{
    var date = new Date();
    date.setTime(dateNum);
    return simpleDate(date);
}

function appendTextNode(xmlDoc, prntEl, name, value)
{
    var ele = xmlDoc.createElement(name);
    prntEl.appendChild(ele);

    var textNode = xmlDoc.createTextNode(value);
    ele.appendChild(textNode);
}

function getEleText(ele)
{
    if (ele == undefined)
    {
        return undefined;
    }

    if (ele.value)
    {
        return ele.value;
    }

    if (ele.textContent)
    {
        return ele.textContent;
    }

    if (ele.innerText)
    {
        return ele.innerText;
    }
}

// Trim if trim is available.
function maybeTrim(str)
{
    return str.trim ? str.trim() : str;
}

// CallerList functions.

function setRowClass(trEl)
{
    var rowId = trEl.id;

    var onlineId = rowId + "-" + colNames[colIdxOnline];
    var onlineEl = document.getElementById(onlineId);
    var online = getEleText(onlineEl);

    var priorityId = rowId + "-" + colNames[colIdxPriority];
    var priorityEl = document.getElementById(priorityId);
    var priority = getEleText(priorityEl);

    log("setRowClass: o: " + online + " p: " + priority);

    trEl.className = priToClass(online, priority);

    if (onlineEl)
    {
        onlineEl.className = (onlineEl.value == 1) ? "on" : "off";
    }

    if (priorityEl)
    {
        priorityEl.className = trEl.className;
    }
}

function addCaller(items)
{
    var callerTableEl = document.getElementById("callerTable");
    var lineNum = items[colIdxLine];
    var trEl;

    // Copy the items to variables.
    var id = items[colIdxId];
    var line = items[colIdxLine];
    items[colIdxTime] = numToDate(items[colIdxTime]);
    var time = items[colIdxTime];
    items[colIdxPriority] = priToChar(items[colIdxPriority]);
    var priority = items[colIdxPriority];
    var online = items[colIdxOnline];
    var name = items[colIdxName];
    var topic = items[colIdxTopic];

    var rowId = allCallers ? ("row_" + numCallers) : ("caller_" + line);

    log("addCaller: rId=" + rowId + " i=" + id + " l=" + line +
        " t=" + time + " p=" + priority + " o=" + online + " n=" + name +
        " t=" + topic);

    // Rather than messing with hidden columns in the table the Id, which is
    // needed later but is not shown, is treated as a special case.
    lineToId[line] = id;

    trEl = document.getElementById(rowId);
    if (!trEl)
    {
        // Add the row if it does not exist.
        trEl = document.createElement("tr");
        callerTableEl.appendChild(trEl);
        numCallers++;
        trEl.id = rowId;

        for (var idx in items)
        {
            if (idx == colIdxId)
            {
                // The Id column is never displayed.
                continue;
            }

            if (!editMode && (idx == colIdxOnline))
            {
                // For list mode there is no online column.
                continue;
            }

            var itemText = items[idx];
            var tdEl = document.createElement("td");
            trEl.appendChild(tdEl);
            var colId = colNames[idx];
            var cellId = rowId + "-" + colId;

            if (editMode)
            {
                if ((idx == colIdxLine) || (idx == colIdxTime))
                {
                    tdEl.id = cellId;
                    var itemTn = document.createTextNode("B");
                    tdEl.appendChild(itemTn);
                }
                else
                {
                    var inEl = document.createElement("input");
                    inEl.id = cellId;

                    if (idx == colIdxOnline)
                    {
                        inEl.type = "button";
                        inEl.onclick = onlineToggle;
                        inEl.style.cssText = "width:96%";
                    }
                    else if (idx == colIdxPriority)
                    {
                        inEl.type = "button";
                        inEl.onclick = priorityCycle;
                        inEl.style.cssText = "width:96%";
                    }
                    else
                    {
                        inEl.onfocus = fieldSave;
                        inEl.onblur = fieldSend;
                        inEl.onkeyup = fieldKey;
                        var width = (idx == colIdxTopic) ? 99 : 98;
                        inEl.style.cssText =
                            "width: " + width + "%";
                    }

                    // For IE 8 the button must be added after the type has
                    // been changed to "button".
                    tdEl.appendChild(inEl);
                }
            }
            else
            {
                tdEl.id = cellId;
                var itemTn = document.createTextNode("X");
                tdEl.appendChild(itemTn);
            }
        }
    }

    // At this point the row exists regardless of mode.  We only need to
    // update by Ids in the document.

    for (var idx in items)
    {
        var itemText = items[idx];

        if (!editMode && (idx == colIdxOnline))
        {
            // There is no online column in list mode.
            continue;
        }

        var colId = colNames[idx];
        var cellId = rowId + "-" + colId;
        var itemEl = document.getElementById(cellId);

        if (itemEl)
        {
            if (itemEl.firstChild) // Could be done with instanceof.
            {
                var itemTn = document.createTextNode(itemText);
                itemEl.replaceChild(itemTn, itemEl.firstChild);
            }
            else
            {
                itemEl.value = itemText;
            }
        }
    }

    setRowClass(trEl);
}

function deleteCallers()
{
    log("Start of deleteCallers.");

    var callerTableEl = document.getElementById("callerTable");

    // The first child is the column header of the callerTable, so leave that.
    // Note this is done rather than calling deleteRow() on the table
    // object as deleteRow() seems to only work with statically created rows
    // for IE 8.
    while (callerTableEl.childNodes.length > emptyCallerLen)
    {
        callerTableEl.removeChild(callerTableEl.lastChild);
    }

    numCallers = 0;
}

// Log by appending to the body.
function log(msg)
{
    if (!debug)
    {
        return;
    }

    var date = simpleDate(new Date());

    var logTN = document.createTextNode(date + ": " + msg);
    document.body.appendChild(logTN);

    var brEl = document.createElement("br");
    document.body.appendChild(brEl);
}

function setFlash(flashOn)
{
    log("Turning flash " + (flashOn ? "on" : "off") + ".");
    document.body.className = "big " + (flashOn ? "high" : "background");
}

function handleFlash()
{
    log("Start handleFlash.  server=" + nowServerMsec + " flash=" +
        flashServerMsec + " duration=" + flashDuration + " limit=" +
        (flashServerMsec + flashDuration));

    // It's up to the client to decide how and for how long to flash.  This
    // client flashes 0 - 7.5 seconds after the flash.
    if (flashDuration && flashServerMsec &&
        ((nowServerMsec >= flashServerMsec) &&
         (nowServerMsec <= (flashServerMsec + flashDuration))))
    {
        setFlash(true);
    }
    else
    {
        setFlash(false);
    }
}

function deleteChildren(elem)
{
    while (elem.firstChild)
    {
        elem.removeChild(elem.firstChild);
    }
}

function removeFieldClasses()
{
    var inputEls = document.getElementsByTagName("input");
    for (var i = 0; i < inputEls.length; i++)
    {
        var inputEl = inputEls[i];
        if (inputEl.type != "button")
        {
            inputEl.className = "";
        }
    }

    var messageEl = document.getElementById("message");
    messageEl.className = "";
}

function ajaxHandler()
{
    if (paused)
    {
        return;
    }

    if (ah.readyState != 4)
    {
        return;
    }

    log("Start of ajaxHandler.  offset=" + offset);

    nowClientMsec = (new Date()).getTime();
    nowServerMsec = nowClientMsec - offset;
    rtt = nowClientMsec - startClientMsec;
    document.getElementById("rtt").innerHTML = rtt;

    // readyState is 4 which is a final state.  Update the time with a
    // descriptive timestamp.
    document.getElementById("time").innerHTML = numToDate(
        nowClientMsec - offset);

    // Load again in 5 seconds regardless of the HTTP status but only if
    // there is no call pending.  Also, all callers implies a huge resource
    // hungry list that the viewer wants to view without interruption.
    if (!pendingCount && !allCallers)
    {
        // Adjust some small amount to approach being close to the middle
        // of a the first second (the 500) that is a multiple of rate.  Error
        // is positive when we are ahead of where we should be.  RTT is added
        // since it is effectively part of the total cycle.  Server timestamps
        // are used since that is what is displayed.
        //
        // For example, if the current server time is 6300 msecs then if there
        // was no adjustment this callback would next be called in 6300 + rate +
        // rtt = 11300 msecs + rtt.  The adjustment will get it closer to
        // 10500 msecs.  The 500 is used to keep it in the middle of the first
        // second rather then alternating between, say, 9999 msecs and 15001
        // msecs if the beginning of the first second was targeted.  9999 msecs
        // would be rounded down to 9 seconds secs when displayed.
        if (synced)
        {
            var error = ((((nowServerMsec + rtt) - 500) + halfRate) % rate) -
                halfRate;
            // To prevent any oscillation the "/ 2" is used to limit the rate
            // that the rate is adjusted.
            var rateOffset = -parseInt(error / 2);
        }
        else
        {
            if (randomized)
            {
                // Not synced.  Don't bother to adjust the timeout.
                var rateOffset = 0;
            }
            else
            {
                // Not synced and just starting up.  For the first timer wait
                // some random amount of time.  This should make the updates
                // from all the clients more evenly distributed spaced.
                var rateOffset = -parseInt(rate * Math.random());
                randomized = true;
            }
        }

        pendingCount++;
        setTimeout("updateCallback()", rate + rateOffset);
    }

    // Update the background.
    handleFlash();

    var httpStatusEl = document.getElementById("httpStatus");
    httpStatusEl.innerHTML = ah.status;
    if (ah.status != 200)
    {
        httpStatusEl.className = "high";
        return;
    }
    else
    {
        httpStatusEl.className = "medium";
    }

    // The XML returned by the AJAX service.
    var respXML = ah.responseXML; // Type is XMLDocument.

    if (!respXML)
    {
        var messageEl = document.getElementById("message");
        var nullXmlTn = document.createTextNode("The AJAX service returned " +
            "null XML.  This should not happen.");
        messageEl.replaceChild(nullXmlTn, messageEl.firstChild);
        return;
    }

    var callerList = respXML.documentElement;
    for (var nodeIdx = 0; nodeIdx < callerList.childNodes.length; nodeIdx++)
    {
        var node = callerList.childNodes[nodeIdx];
        if (node.nodeType != 1)
        {
            // Only consider real element child nodes.
            continue;
        }

        if (node.nodeName == "redirect")
        {
            redirect = node.firstChild.nodeValue;
            setTimeout("redirectCallback()", rate);
        }
        else if (node.nodeName == "callers")
        {
            // For now assume that if there are any lines then it will be a
            // complete list.
            if (!editMode)
            {
                deleteCallers();
            }

            for (var lineIdx = 0; lineIdx < node.childNodes.length; lineIdx++)
            {
                var cline = node.childNodes[lineIdx];
                if (cline.nodeType != 1)
                {
                    // Only consider real element child nodes.
                    continue;
                }

                var items = new Array();
                var lineNum = 0;
                for (var itemIdx = 0; itemIdx < cline.childNodes.length;
                     itemIdx++)
                {
                    var item = cline.childNodes[itemIdx];
                    if (item.nodeType != 1)
                    {
                        // Only consider real element child nodes.
                        continue;
                    }

                    if (item.firstChild)
                    {
                        items.push(item.firstChild.nodeValue);
                    }
                    else
                    {
                        items.push("");
                    }
                }

                addCaller(items);
            }
        }
        else
        {
            var ele = document.getElementById(node.nodeName);
            if (ele && (ele.type != "button"))
            {
                // Convert what was received to some descriptive format.
                var eleDesc;
                if (node.nodeName == "modified")
                {
                    modified = node.firstChild.nodeValue;
                    eleDesc = numToDate(modified);
                }
                else if (node.nodeName == "time")
                {
                    nowServerMsec = node.firstChild.nodeValue;
                    eleDesc = numToDate(nowServerMsec);

                    // Keep track of the offset relative to the server's
                    // time.
                    offset = nowClientMsec - nowServerMsec;
                    log("Updated offset to " + offset);
                }
                else
                {
                    if (node.firstChild)
                    {
                        eleDesc = node.firstChild.nodeValue;
                    }
                    else
                    {
                        eleDesc = "";
                    }
                }

                var descTn = document.createTextNode(eleDesc);
                if (ele.firstChild)
                {
                    ele.replaceChild(descTn, ele.firstChild);
                }
                else
                {
                    ele.appendChild(descTn);
                }
            }
            else
            {
                if ((node.nodeName == "flash") && node.firstChild)
                {
                    flashServerMsec = parseInt(node.firstChild.nodeValue);
                    handleFlash();
                }
                else if ((node.nodeName == "refreshRate") && node.firstChild)
                {
                    rate = parseInt(node.firstChild.nodeValue);
                    halfRate = parseInt(rate / 2);
                }
            }
        }
    }

    // Get a copy of the response XML once and reuse it from this point on.
    // This is done due to a lack of a platform independent way of creating
    // XML documents.
    if (!updateXML)
    {
        updateXML = respXML;
        deleteChildren(updateXML.documentElement);
    }

    // Now that we are done processing the XML from AJAX see if there were
    // any updates while the AJAX was processing.  If not then all data
    // was sent successfully without any race condition and updateXML can be
    // striped of all elements so that it can be reused.
    var updatesEl = respXML.getElementsByTagName("updates")[0];
    if (updatesEl)
    {
        var respUpdates = updatesEl.firstChild.nodeValue;
        if (respUpdates == updates)
        {
            updated = false;
            deleteChildren(updateXML.documentElement);
            removeFieldClasses();
        }
    }
}

// Redirect so the user can regain the required auth level.
function redirectCallback()
{
    window.location = redirect;
}

// Used to distinguish between being called as a callback and directly.
function updateCallback()
{
    pendingCount--;
    update();
}

// From http://www.webdeveloper.com/forum/showthread.php?t=187378
function xml2Str(xmlNode)
{
    try
    {
        // Gecko-based browsers, Safari, Opera.
        return (new XMLSerializer()).serializeToString(xmlNode);
    }
    catch (e)
    {
        try
        {
            // Internet Explorer.
            return xmlNode.xml;
        }
        catch (e)
        {
            log("xml2Str() failed");
        }
    }

    return false;
}

function flash()
{
    setFlash(1);
    updateXMLAppend("flash", 1);
    update();
}

function update()
{
    if (xh)
    {
        // Cancel any existing AJAX connection.  This does not seem to work.
        xh.cancel();
    }

    // Only works on new browsers.  Setup.
    ah = new XMLHttpRequest();
    ah.onreadystatechange = ajaxHandler;

    ah.open("POST", "../ajax/callerlist.php", true); // Third arg - async.
    ah.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    startClientMsec = (new Date()).getTime();
    var postStr = "modified=" + modified + "&allCallers=" + allCallers +
        "&editMode=" + editMode;
    if (editMode && updateXML && updated)
    {
        var updateXMLText = xml2Str(updateXML);
        log("updateXML: " + updateXMLText);
        postStr += "&updateXML=" + encodeURIComponent(updateXMLText);
    }
    log("post string: " + postStr);
    ah.send(postStr);
}

function pause()
{
    paused = document.getElementById("pause").checked;
    document.title = title + (paused ? " (paused)" : "");
    if (!paused)
    {
        update();
    }
}

function sync()
{
    synced = document.getElementById("sync").checked;
    if (!synced)
    {
        // If switching from synced mode to non-synced mode we need to
        // re-randomize the time.
        randomized = false;
    }
}

// Calling the following function "all" does not work for Android.
function allC()
{
    allCallers = document.getElementById("all").checked;
    log("Start of all.  allCallers=" + allCallers);
    modified = 0; // Force full update when toggled.
    update();
}

// JavaScript events.

function init()
{
    // Make a note of the number of nodes in an empty caller table so it can
    // blanked out later.
    var callerTableEl = document.getElementById("callerTable");
    emptyCallerLen = callerTableEl.childNodes.length;

    // Save the initial title.  Use this to guess what the caller is.
    title = document.title;
    editMode = title.toLowerCase().indexOf("edit") > -1;

    // The "traditional" event handler will be used for all fields.

    var pauseEl = document.getElementById("pause");
    pauseEl.onclick = pause;
    pauseEl.checked = false;

    var syncEl = document.getElementById("sync");
    syncEl.onclick = sync;
    syncEl.checked = false;

    var allEl = document.getElementById("all");
    if (allEl)
    {
        allEl.onclick = allC;
        allEl.checked = false;
    }

    var flashEl = document.getElementById("flash");
    if (flashEl)
    {
        flashEl.onclick = flash;
    }

    var updateEl = document.getElementById("update");
    updateEl.onclick = update;

    var messageEl = document.getElementById("message");
    messageEl.onfocus = fieldSave;
    messageEl.onblur = fieldSend;
    messageEl.onkeyup = fieldKey;

    update();
}

// Register the above initialization function.  This is the only JavaScript
// code that is not in a function.
window.onload = init;

function updateXMLAppend(fieldId, value)
{
    log("updateXMLAppend: fieldId=" + fieldId + " value=" + value);

    if (!updateXML)
    {
        log("updateXMLAppend: And update while updateXML is still null. " +
            "Ignoring.");
        return;
    }

    var rootEl = updateXML.documentElement;
    updates++;
    updated = true;

    var updatesEl = updateXML.getElementsByTagName("updates")[0];
    if (updatesEl)
    {
        var updatesTn = updateXML.createTextNode(updates);
        updatesEl.replaceChild(updatesTn, updatesEl.firstChild);
    }
    else
    {
        appendTextNode(updateXML, rootEl, "updates", updates);
    }

    if ((fieldId == "flash") || (fieldId == "message"))
    {
        var messageEl = updateXML.getElementsByTagName(fieldId)[0];
        if (messageEl)
        {
            var messageTn = updateXML.createTextNode(value);
            messageEl.replaceChild(messageTn, messageEl.firstChild);
        }
        else
        {
            appendTextNode(updateXML, rootEl, fieldId, value);
        }
    }
    else
    {
        // Merging in new caller information is too complicated.  Just append
        // the entire row.  Since the XML is processed in order the last row
        // will take precedence.
        var callersEl = updateXML.getElementsByTagName("callers")[0];
        if (!callersEl)
        {
            callersEl = updateXML.createElement("callers");
            rootEl.appendChild(callersEl);
        }

        var callerEl = updateXML.createElement("caller");
        callersEl.appendChild(callerEl);

        var rowId = fieldId.split("-")[0];

        var lineEl = document.getElementById(rowId + "-line");
        var lineText = getEleText(lineEl);
        appendTextNode(updateXML, callerEl, "id", lineToId[lineText]);

        for (var i in colNames)
        {
            var colId = colNames[i];
            var cellId = rowId + "-" + colId;
            var cellEl = document.getElementById(cellId);

            if (cellEl)
            {
                var cellText = getEleText(cellEl);
                if (cellText == undefined)
                {
                    cellText = "";
                }

                if (i == colIdxPriority)
                {
                    cellText = charToPri(cellText);
                }

                appendTextNode(updateXML, callerEl, colId, cellText);
            }
        }
    }

    var fieldEl = document.getElementById(fieldId);
    if (fieldEl && (fieldEl.type != "button"))
    {
        fieldEl.className = "pending";
    }
}

// Cross platform target finding.

function getEvent(e)
{
    if (e)
    {
        return e;
    }
    else
    {
        return window.event;
    }
}

function getTarget(e)
{
    if (e.target)
    {
        return e.target;
    }
    else if (e.srcElement)
    {
        return e.srcElement;
    }
}

function fieldSave(e)
{
    var e = getEvent(e);
    var target = getTarget(e);

    valueLast = target.value;
}

function fieldSend(e)
{
    var e = getEvent(e);
    var target = getTarget(e);

    var id = target.id;
    var valueOld = target.value;
    target.value = maybeTrim(target.value);
    if ((id == "message") && (target.value != valueOld) &&
            (valueOld[valueOld.length - 1] == "\n"))
    {
        // For the message field allow at most one \n at the end.
        target.value = target.value + "\n";
    }
    if (target.value == valueLast)
    {
        target.className = "";
    }
    else
    {
        updateXMLAppend(id, target.value);
        update();
        valueLast = target.value;
    }
}

function fieldKey(e)
{
    var e = getEvent(e);
    var target = getTarget(e);
    var keyCode = getKeyCode(e);

    switch (keyCode)
    {
        case 13: // return
            if (e.ctrlKey && (target.id == "message"))
            {
                // If control was pressed then flash as well as send.
                flash();
            }
            log("fieldKey: return key code");
            fieldSend(e);
            break;
        case 27: // escape
            log("fieldKey: escape key code.  valueLast: " + valueLast);
            target.className = "";
            target.value = valueLast;
            break;
        case 38: // up arrow
            // Ordinary cursor movement for message.
            onlineToggle(e, true);
            break;
        case 40: // down arrow
            if (e.ctrlKey)
            {
                // if ctrl-down clear everything first.
                clear(e);
            }
            onlineToggle(e, false);
            break;
        case 46: // delete
            if (e.ctrlKey)
            {
                clear(e);
                fieldSend(e);
            }
            break;
        default:
            if (target.value != valueLast)
            {
                target.className = "changed";
            }
    }
}

function getKeyCode(e) {
    return e.which ? e.which : e.keyCode;
}

function onlineToggle(e)
{
    var e = getEvent(e);
    var target = getTarget(e);

    var newValue = 1 - target.value;
    if (target.type != "button")
    {
        // This means that the event is for a field.  Figure out the
        // corresponding button and use the variable argument list for the
        // target value.
        var rowId = target.id.split("-")[0];
        var onlineId = rowId + "-online";
        target = document.getElementById(onlineId);
        if (!target)
        {
            // We can't figure out the corresponding online button.  This
            // probably means this is the message field, so ignore.
            return;
        }

        if (arguments.length == 2)
        {
            newValue = arguments[1] ? 1 : 0;
        }
    }

    if (target.value == newValue)
    {
        // Nothing to do.
        return;
    }
    target.value = newValue;

    log("onlineToggle: value=" + target.value);
    setRowClass(target.parentNode.parentNode);
    log("onlineToggle: after setRowClass");

    var id = target.id;
    updateXMLAppend(id, target.value);
    update();
}

function clear(e)
{
    var target = getTarget(e);

    if (target.id == "message")
    {
        // Just clear the massage field.
        target.value = "";
    }
    else
    {
        // Find the corresponding row and clear that.
        var rowId = target.id.split("-")[0];

        var priorityId = rowId + "-priority";
        var nameId = rowId + "-name";
        var topicId = rowId + "-topic";

        var priorityEl = document.getElementById(priorityId);
        var nameEl = document.getElementById(nameId);
        var topicEl = document.getElementById(topicId);

        priorityEl.value = "M";
        nameEl.value = "";
        topicEl.value = "";
    }
}

function priorityCycle(e)
{
    var e = getEvent(e);
    var target = getTarget(e);

    // It should go M, H, L, M, H, L
    var priority = charToPri(target.value);
    priority = (((priority - 4 + 3) - 1) % 3) + 4;
    target.value = priToChar(priority);

    log("priorityCycle: value=" + target.value);
    setRowClass(target.parentNode.parentNode);
    log("priorityCycle: after setRowClass");

    var id = target.id;
    updateXMLAppend(id, target.value);
    update();
}
