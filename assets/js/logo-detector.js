document.addEventListener('DOMContentLoaded', function() {
    const logo = document.getElementById('logo-img');
    const navbarBrand = document.querySelector('.navbar-brand');
    
    // Função para verificar se a imagem existe
    function checkImageExists(imageUrl, callback) {
        const img = new Image();
        img.onload = function() {
            callback(true);
        };
        img.onerror = function() {
            callback(false);
        };
        img.src = imageUrl;
    }

    // Função para mostrar o texto alternativo
    function showAltText() {
        const logoText = document.createElement('span');
        logoText.id = 'logo-text';
        logoText.style.fontSize = '1.2rem';
        logoText.style.fontWeight = 'bold';
        logoText.textContent = 'SISTEMA DE EMPRÉSTIMOS';
        
        // Remove a imagem se existir
        if (logo) {
            logo.remove();
        }
        
        // Adiciona o texto
        navbarBrand.appendChild(logoText);
        
        // Aplica o fundo
        navbarBrand.style.background = 'rgba(250, 250, 250, 0.9)';
        navbarBrand.style.backdropFilter = 'blur(1px)';
        navbarBrand.style.webkitBackdropFilter = 'blur(1px)';
    }

    // Verifica se a imagem existe e tem a grafia correta
    if (logo) {
        const imageUrl = logo.src;
        const correctPath = imageUrl.toLowerCase().includes('logo.png');
        
        checkImageExists(imageUrl, function(exists) {
            if (!exists || !correctPath) {
                showAltText();
            } else {
                // Se a imagem existe e tem a grafia correta, continua com a análise de luminosidade
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                logo.onload = function() {
                    canvas.width = logo.width;
                    canvas.height = logo.height;
                    ctx.drawImage(logo, 0, 0);
                    
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const data = imageData.data;
                    
                    let totalLuminance = 0;
                    let pixelCount = 0;
                    
                    for (let i = 0; i < data.length; i += 4) {
                        if (data[i + 3] > 0) {
                            const r = data[i];
                            const g = data[i + 1];
                            const b = data[i + 2];
                            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                            
                            totalLuminance += luminance;
                            pixelCount++;
                        }
                    }
                    
                    const averageLuminance = totalLuminance / pixelCount;
                    
                    if (averageLuminance < 0.5) {
                        navbarBrand.style.background = 'rgba(250, 250, 250, 0.9)';
                        navbarBrand.style.backdropFilter = 'blur(1px)';
                        navbarBrand.style.webkitBackdropFilter = 'blur(1px)';
                    } else {
                        navbarBrand.style.background = 'none';
                        navbarBrand.style.backdropFilter = 'none';
                        navbarBrand.style.webkitBackdropFilter = 'none';
                    }
                };
            }
        });
    } else {
        showAltText();
    }
}); 