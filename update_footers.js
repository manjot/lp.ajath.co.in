const fs = require('fs');

const indexHtml = fs.readFileSync('index.html', 'utf8');

// Extract footer and scripts from index.html
const footerStart = indexHtml.indexOf('<footer class="bg-gradient-to-br');
const footerAndScripts = indexHtml.substring(footerStart);

const filesToUpdate = [
    'delivery-app.html',
    'groceries-app.html',
    'medical-app.html',
    'property-app.html',
    'social-app.html',
    'taxi-app.html'
];

for (const file of filesToUpdate) {
    if (fs.existsSync(file)) {
        let content = fs.readFileSync(file, 'utf8');
        
        // Find where the old footer starts
        const oldFooterStart = content.indexOf('<!-- Footer -->');
        if (oldFooterStart !== -1) {
            content = content.substring(0, oldFooterStart) + footerAndScripts;
            fs.writeFileSync(file, content);
            console.log(`Updated ${file}`);
        } else {
            console.log(`Could not find old footer in ${file}`);
        }
    }
}
