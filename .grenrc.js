function getAuthor(placeholders) {
    if (placeholders.author === 'ole1986') {
        // skip owner
        return '';
    }
    return `- ${placeholders.author ? `@${placeholders.author}` : name}`;
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