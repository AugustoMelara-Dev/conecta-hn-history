import { expect, test } from '@playwright/test';

const scenarios = [
    {
        slug: 'casa-brasa-bistro',
        label: 'Restaurante',
        showcase: 'Combos, platos y bebidas',
        playbook: 'Descubre, pide y vuelve',
        panelTestId: 'product-panel',
        panelText: 'Brasa Signature Burger',
    },
    {
        slug: 'patio-casual-bistro',
        label: 'Restaurante casual',
        showcase: 'Menu pensado para rotacion',
        playbook: 'Abre apetito, mueve ticket y empuja recompra',
        panelTestId: 'product-panel',
        panelText: 'Burger combo de la casa',
    },
    {
        slug: 'alto-brew-cafeteria',
        label: 'Cafeteria',
        showcase: 'Marca que invita a quedarse',
        playbook: 'Presenta marca, activa pickup y construye rutina',
        panelTestId: 'product-panel',
        panelText: 'Cold brew de temporada',
    },
    {
        slug: 'movil-center-hn',
        label: 'Tienda de celulares',
        showcase: 'Equipos que mueven la vitrina',
        playbook: 'Descubre, compara y cierra',
        panelTestId: 'product-panel',
        panelText: 'iPhone 15 Pro 256GB',
    },
    {
        slug: 'titan-flagship-mobile',
        label: 'Tienda de celulares premium',
        showcase: 'Retail de alta confianza',
        playbook: 'Enamora con flagship, empuja bundles y transmite respaldo',
        panelTestId: 'product-panel',
        panelText: 'Galaxy Ultra flagship bundle',
    },
    {
        slug: 'ferreteria-ferremax-hn',
        label: 'Ferreteria',
        showcase: 'Catalogo para necesidades reales',
        playbook: 'Resuelve la necesidad, cotiza rapido y gana recurrencia',
        panelTestId: 'product-panel',
        panelText: 'Kit de herramientas para obra',
    },
    {
        slug: 'bufete-lex-hn',
        label: 'Firma legal',
        showcase: 'Areas de practica visibles',
        playbook: 'Filtra el caso, agenda y prepara la reunion',
        panelTestId: 'service-panel',
        panelText: 'Consulta legal inicial',
    },
    {
        slug: 'lex-corporativo-hn',
        label: 'Bufete corporativo',
        showcase: 'Servicios legales que hablan de negocio',
        playbook: 'Enmarca el problema, agenda la reunion y da continuidad',
        panelTestId: 'service-panel',
        panelText: 'Sesion de diagnostico corporativo',
    },
    {
        slug: 'clinica-salud-vital',
        label: 'Clinica o centro de salud',
        showcase: 'Especialidades y consultas',
        playbook: 'Orienta, agenda y da seguimiento',
        panelTestId: 'service-panel',
        panelText: 'Evaluacion general',
    },
    {
        slug: 'clinica-dental-sonrisa',
        label: 'Clinica dental',
        showcase: 'Explica el siguiente paso correcto',
        playbook: 'Orienta al paciente, agenda la valoracion y reduce ansiedad',
        panelTestId: 'service-panel',
        panelText: 'Valoracion odontologica',
    },
    {
        slug: 'faro-growth-advisory',
        label: 'Consultoria',
        showcase: 'Servicios con foco',
        playbook: 'Diagnostica, propone y acompana',
        panelTestId: 'service-panel',
        panelText: 'Discovery call comercial',
    },
    {
        slug: 'faro-finanzas-advisory',
        label: 'Consultoria financiera',
        showcase: 'Convierte complejidad en claridad',
        playbook: 'Diagnostica la caja, alinea la decision y acompana la ejecucion',
        panelTestId: 'service-panel',
        panelText: 'Diagnostico de flujo de caja',
    },
    {
        slug: 'barberia-norte-pro',
        label: 'Barberia o salon',
        showcase: 'Cortes y grooming',
        playbook: 'Muestra estilo, llena agenda y empuja promos',
        panelTestId: 'service-panel',
        panelText: 'Corte premium con barba',
    },
    {
        slug: 'district-barber-lounge',
        label: 'Barber premium',
        showcase: 'Grooming como experiencia',
        playbook: 'Activa deseo, llena slots premium y empuja recurrencia',
        panelTestId: 'service-panel',
        panelText: 'Ritual premium de barberia',
    },
    {
        slug: 'aura-spa-studio',
        label: 'Spa y bienestar',
        showcase: 'Narrativa mas sensorial',
        playbook: 'Transmite calma, activa reserva y fomenta recurrencia',
        panelTestId: 'service-panel',
        panelText: 'Ritual facial y relajacion',
    },
    {
        slug: 'taller-motor-pro',
        label: 'Taller o servicio tecnico',
        showcase: 'Servicios tecnicos y repuestos',
        playbook: 'Diagnostica, aprueba y entrega',
        panelTestId: 'product-panel',
        panelText: 'Filtro premium para sedan',
    },
    {
        slug: 'repuestos-turbo-center',
        label: 'Tienda de repuestos',
        showcase: 'Catalogo tecnico para venta real',
        playbook: 'Detecta la pieza, valida la solucion y abre el siguiente paso',
        panelTestId: 'product-panel',
        panelText: 'Kit premium de frenos delanteros',
    },
];

test.describe('Micrositios verticales', () => {
    for (const scenario of scenarios) {
        test(`${scenario.slug} renders a specialized storefront`, async ({ page }) => {
            await page.goto(`/t/${scenario.slug}`);

            await expect(page.getByTestId('tenant-microsite')).toBeVisible();
            await expect(page.getByTestId('storefront-vertical-label')).toContainText(scenario.label);
            await expect(page.getByTestId('vertical-showcase')).toContainText(scenario.showcase);
            await expect(page.getByTestId('vertical-playbook')).toContainText(scenario.playbook);
            await expect(page.getByTestId(scenario.panelTestId)).toContainText(scenario.panelText);
        });
    }
});
