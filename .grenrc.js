
function getAuthor(placeholders) {
    if (placeholders.author === 'ole1986') {
        // skip owner
        return '';
    }

    if (placeholders.author === null) {
        // skip when no author could be found
        return '';
    }

    return `- @${placeholders.author}`;
}

function parseCommitLine(placeholders) {
    return `- ${placeholders.message} ${getAuthor(placeholders)}`
}

module.exports = {
    dataSource: "commits",
    "template": {
        commit: parseCommitLine
    }
}