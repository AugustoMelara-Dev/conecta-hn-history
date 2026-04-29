<?php

declare(strict_types=1);

use App\Domain\Access\Enums\Role;

return [
    'defaults' => [
        'retail' => 'retail_growth',
        'food' => 'food_growth',
        'service' => 'service_growth',
        'hybrid' => 'hybrid_growth',
    ],

    'profiles' => [
        'retail_growth' => [
            'team_title' => 'Equipo recomendado para retail',
            'team_copy' => 'Empieza con roles que aceleren atencion, inventario y cierre. No necesitas un organigrama gigante, sino claridad operativa.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Atencion digital', 'copy' => 'Responde consultas, cotizaciones y mensajes antes de que el interes se enfrie.', 'role' => Role::OPERATOR->value, 'when' => 'Desde tus primeras conversaciones activas', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Responsable de inventario', 'copy' => 'Mantiene catalogo, stock y orden de colecciones sin improvisacion.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya manejas varias lineas o rotacion constante', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Coordinacion comercial', 'copy' => 'Ordena promociones, seguimiento y reportes para vender con mas criterio.', 'role' => Role::ADMIN->value, 'when' => 'Cuando necesitas crecer ticket y recompra', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para retail',
            'automation_copy' => 'Empieza por automatizaciones que empujen respuesta rapida, recompra y mejor conversion desde WhatsApp.',
            'automation_playbook' => [
                ['eyebrow' => 'Lead', 'title' => 'Seguimiento de cotizacion', 'copy' => 'Cada consulta debe terminar en cotizacion, bundle o siguiente paso claro.', 'trigger' => 'Consulta nueva o lead sin respuesta', 'state' => 'retail_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Recompra', 'title' => 'Campanas de productos clave', 'copy' => 'Mueve novedades, combos y rotacion de inventario a clientes y seguidores.', 'trigger' => 'Semana comercial o lanzamiento', 'state' => 'audience_ready', 'route_key' => 'marketing'],
                ['eyebrow' => 'Upsell', 'title' => 'Bundles y accesorios', 'copy' => 'Convierte cada venta en una oportunidad de ticket mas alto.', 'trigger' => 'Producto principal publicado o campaña activa', 'state' => 'products', 'route_key' => 'products'],
            ],
        ],
        'food_growth' => [
            'team_title' => 'Equipo recomendado para comida',
            'team_copy' => 'La operacion mejora cuando caja, cocina y promociones se reparten con responsabilidad clara.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Caja o pedidos', 'copy' => 'Alguien debe confirmar ordenes y sostener atencion al cliente con ritmo.', 'role' => Role::OPERATOR->value, 'when' => 'Desde el primer flujo de pedidos', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Lider de cocina u operacion', 'copy' => 'Coordina menu, tiempos de respuesta y orden diario.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya mueves volumen o varias categorias', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Marketing y recompra', 'copy' => 'Sostiene promos, especiales y comunicacion para que la marca vuelva a vender.', 'role' => Role::ADMIN->value, 'when' => 'Cuando quieres subir ticket y recurrencia', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para comida',
            'automation_copy' => 'En comida, la automatizacion debe proteger velocidad y recompra antes que complejidad.',
            'automation_playbook' => [
                ['eyebrow' => 'Promos', 'title' => 'Especiales de la semana', 'copy' => 'Mueve lunch menus, combos y platos gancho con cadencia semanal.', 'trigger' => 'Nuevo menu o promo activa', 'state' => 'food_sales_base', 'route_key' => 'marketing'],
                ['eyebrow' => 'Pedidos', 'title' => 'Reactivacion por WhatsApp', 'copy' => 'Recupera clientes que ya compraron o preguntaron pero aun no vuelven.', 'trigger' => 'Dias sin recompra', 'state' => 'audience_ready', 'route_key' => 'marketing'],
                ['eyebrow' => 'Operacion', 'title' => 'Recordatorio de horario y cobertura', 'copy' => 'Reduce friccion cuando cambian horarios, delivery o pickup.', 'trigger' => 'Cambio operativo o alta demanda', 'state' => 'service_channel', 'route_key' => 'profile'],
            ],
        ],
        'service_growth' => [
            'team_title' => 'Equipo recomendado para servicios',
            'team_copy' => 'Servicios escalan mejor cuando oferta, agenda y seguimiento tienen responsables claros.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Recepcion o coordinacion', 'copy' => 'Centraliza consultas, agenda y seguimiento sin depender de una sola persona tecnica.', 'role' => Role::OPERATOR->value, 'when' => 'Desde que empiezas a recibir leads o reservas', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Responsable de operaciones', 'copy' => 'Ordena disponibilidad, calidad de atencion y cumplimiento diario.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya hay varias citas, servicios o staff', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Seguimiento comercial', 'copy' => 'Hace que discovery, propuestas y recompra no se pierdan en el dia a dia.', 'role' => Role::ADMIN->value, 'when' => 'Cuando el embudo necesita continuidad y reportes', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para servicios',
            'automation_copy' => 'Las mejores automatizaciones en servicios reducen tiempo muerto entre consulta, reserva y seguimiento.',
            'automation_playbook' => [
                ['eyebrow' => 'Lead', 'title' => 'Respuesta a consulta inicial', 'copy' => 'Convierte contacto frio en discovery, valoracion o cotizacion con menos fuga.', 'trigger' => 'Lead nuevo o mensaje entrante', 'state' => 'service_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Agenda', 'title' => 'Confirmacion y recordatorio', 'copy' => 'Reduce ausencias y mejora preparacion antes de la cita.', 'trigger' => 'Reserva creada o cita proxima', 'state' => 'booking_ready', 'route_key' => 'appointments'],
                ['eyebrow' => 'Nutricion', 'title' => 'Seguimiento post-reunion', 'copy' => 'Mantiene vivo el prospecto despues de discovery, diagnostico o valoracion.', 'trigger' => 'Cita atendida o lead sin cierre', 'state' => 'leads', 'route_key' => 'marketing'],
            ],
        ],
        'hybrid_growth' => [
            'team_title' => 'Equipo recomendado para negocio mixto',
            'team_copy' => 'El negocio mixto gana cuando alguien cuida producto, alguien cuida servicio y ambos comparten seguimiento.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Coordinacion de atencion', 'copy' => 'Une pedidos, consultas y reservas para que el cliente no sienta dos negocios distintos.', 'role' => Role::OPERATOR->value, 'when' => 'Desde el primer flujo mixto', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Lider de operacion mixta', 'copy' => 'Balancea catalogo, servicio, tiempos y cobertura.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya se cruzan dos frentes de ingreso', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Growth y seguimiento', 'copy' => 'Integra CRM, promociones y reportes para no perder oportunidades.', 'role' => Role::ADMIN->value, 'when' => 'Cuando quieres crecer sin aumentar caos', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para negocio mixto',
            'automation_copy' => 'Automatiza el siguiente paso correcto segun si el cliente viene por producto, servicio o soporte.',
            'automation_playbook' => [
                ['eyebrow' => 'Mixto', 'title' => 'Seguimiento de producto y servicio', 'copy' => 'Define si la conversacion sigue en pedido, cita o soporte tecnico.', 'trigger' => 'Consulta nueva o cliente recurrente', 'state' => 'hybrid_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Operacion', 'title' => 'Recordatorios de agenda y entrega', 'copy' => 'Reduce perdida entre citas, pedidos y cumplimiento.', 'trigger' => 'Pedido pendiente o cita proxima', 'state' => 'mixed_activity', 'route_key' => 'appointments'],
                ['eyebrow' => 'Recompra', 'title' => 'Campanas segmentadas por necesidad', 'copy' => 'Promociona productos, mantenimientos o upgrades segun el caso.', 'trigger' => 'Base de clientes o leads con contexto', 'state' => 'audience_ready', 'route_key' => 'marketing'],
            ],
        ],
        'dental_clinic' => [
            'team_title' => 'Equipo recomendado para clinica dental',
            'team_copy' => 'Recepcion, coordinacion clinica y seguimiento deben sentirse como un solo frente de confianza para el paciente.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Recepcion clinica', 'copy' => 'Atiende consultas, orienta valoraciones y protege la agenda diaria.', 'role' => Role::OPERATOR->value, 'when' => 'Desde la primera valoracion publicada', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Coordinacion clinica', 'copy' => 'Ordena tratamientos, disponibilidad y continuidad del paciente.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya mueves varias especialidades o doctores', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Seguimiento de pacientes', 'copy' => 'Sostiene recordatorios, reactivacion y planes de tratamiento.', 'role' => Role::ADMIN->value, 'when' => 'Cuando la recompra y continuidad ya pesan en caja', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para clinica dental',
            'automation_copy' => 'La automatizacion aqui debe bajar ansiedad, aumentar asistencia y sostener seguimiento despues de la primera valoracion.',
            'automation_playbook' => [
                ['eyebrow' => 'Valoracion', 'title' => 'Seguimiento post-consulta', 'copy' => 'Convierte valoraciones en tratamientos con mensajes y siguientes pasos claros.', 'trigger' => 'Lead nuevo o valoracion realizada', 'state' => 'service_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Agenda', 'title' => 'Recordatorio de cita', 'copy' => 'Reduce ausencias y prepara mejor a recepcion y paciente.', 'trigger' => 'Cita confirmada o proxima', 'state' => 'booking_ready', 'route_key' => 'appointments'],
                ['eyebrow' => 'Reactivacion', 'title' => 'Campanas de higiene y control', 'copy' => 'Trae de vuelta pacientes para limpiezas, revisiones y continuidad.', 'trigger' => 'Pacientes sin visita reciente', 'state' => 'audience_ready', 'route_key' => 'marketing'],
            ],
        ],
        'corporate_law_firm' => [
            'team_title' => 'Equipo recomendado para bufete corporativo',
            'team_copy' => 'El despacho se ve mas serio cuando intake, coordinacion legal y desarrollo comercial no se mezclan sin criterio.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Intake legal', 'copy' => 'Recoge contexto inicial y filtra mejor antes de ocupar tiempo senior.', 'role' => Role::OPERATOR->value, 'when' => 'Desde los primeros leads B2B', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Coordinacion de cuentas', 'copy' => 'Ordena reuniones, seguimiento y avance comercial del despacho.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya hay varias oportunidades activas', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Desarrollo de negocio', 'copy' => 'Sostiene pipeline, contenido y continuidad con clientes empresa.', 'role' => Role::ADMIN->value, 'when' => 'Cuando quieres convertir mas discovery en contratos', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para legal B2B',
            'automation_copy' => 'Lo valioso aqui es responder rapido, preparar discovery y dar continuidad seria al pipeline.',
            'automation_playbook' => [
                ['eyebrow' => 'Intake', 'title' => 'Respuesta a lead corporativo', 'copy' => 'Clasifica la oportunidad y mueve discovery sin perder tiempo senior.', 'trigger' => 'Lead nuevo o consulta de empresa', 'state' => 'service_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Pipeline', 'title' => 'Seguimiento post-discovery', 'copy' => 'Convierte reuniones en propuesta, segundo contacto o cierre.', 'trigger' => 'Reunion completada o lead tibio', 'state' => 'leads', 'route_key' => 'marketing'],
                ['eyebrow' => 'Autoridad', 'title' => 'Campanas de contenido y reputacion', 'copy' => 'Mantiene al despacho visible con temas de compliance, contratos y riesgo.', 'trigger' => 'Temas clave o trimestre comercial', 'state' => 'pro_marketing', 'route_key' => 'marketing'],
            ],
        ],
        'financial_consultancy' => [
            'team_title' => 'Equipo recomendado para consultoria financiera',
            'team_copy' => 'Discovery, analisis y continuidad comercial tienen que estar bien repartidos para que el cliente sienta metodo.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Coordinacion de discovery', 'copy' => 'Filtra leads, agenda diagnosticos y mantiene contexto de negocio.', 'role' => Role::OPERATOR->value, 'when' => 'Desde el primer flujo de consultas ejecutivas', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Analista o delivery lead', 'copy' => 'Asegura calidad en diagnostico, sesiones y seguimiento.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya se mezclan varios clientes o proyectos', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Growth consultivo', 'copy' => 'Sostiene propuestas, nurturing y visibilidad del expertise.', 'role' => Role::ADMIN->value, 'when' => 'Cuando quieres crecer pipeline sin bajar calidad', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para finanzas',
            'automation_copy' => 'Automatiza discovery, seguimiento y contenido educativo para vender mejor sin sonar generico.',
            'automation_playbook' => [
                ['eyebrow' => 'Discovery', 'title' => 'Respuesta a diagnostico', 'copy' => 'Lleva cada consulta a una llamada util con mejor contexto.', 'trigger' => 'Lead nuevo o formulario ejecutivo', 'state' => 'service_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Nurturing', 'title' => 'Seguimiento despues de reunion', 'copy' => 'Convierte interes en propuesta, segunda sesion o cierre.', 'trigger' => 'Reunion completada o propuesta pendiente', 'state' => 'leads', 'route_key' => 'marketing'],
                ['eyebrow' => 'Autoridad', 'title' => 'Campanas de educacion financiera', 'copy' => 'Mueve contenido sobre flujo, margen y caja para elevar calidad del lead.', 'trigger' => 'Tema de temporada o playbook activo', 'state' => 'pro_marketing', 'route_key' => 'marketing'],
            ],
        ],
        'premium_phone_store' => [
            'team_title' => 'Equipo recomendado para celulares premium',
            'team_copy' => 'Ticket alto y confianza postventa piden un equipo que venda bien y responda mejor.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Asesor de alto ticket', 'copy' => 'Responde consultas y cotizaciones con foco en cierre y confianza.', 'role' => Role::OPERATOR->value, 'when' => 'Desde el primer flujo premium', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Responsable de bundles e inventario', 'copy' => 'Coordina equipos, accesorios y disponibilidad real.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya se cruzan varios modelos y margenes', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Posventa y retencion', 'copy' => 'Empuja upgrades, trade-ins y soporte visible.', 'role' => Role::ADMIN->value, 'when' => 'Cuando la recompra y el respaldo ya pesan en la marca', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para alto ticket',
            'automation_copy' => 'El objetivo aqui es cerrar mejor, subir bundle y sostener confianza despues de la compra.',
            'automation_playbook' => [
                ['eyebrow' => 'Cotizacion', 'title' => 'Seguimiento de alto ticket', 'copy' => 'No pierdas cotizaciones de equipos premium por falta de continuidad.', 'trigger' => 'Lead premium o carrito frio', 'state' => 'retail_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Upsell', 'title' => 'Bundles de accesorios', 'copy' => 'Empuja accesorios y kits como parte natural de la decision.', 'trigger' => 'Producto principal activo', 'state' => 'products', 'route_key' => 'products'],
                ['eyebrow' => 'Posventa', 'title' => 'Campanas de upgrade y soporte', 'copy' => 'Haz visible garantia, soporte y oportunidades de upgrade.', 'trigger' => 'Base de clientes o lanzamientos', 'state' => 'pro_marketing', 'route_key' => 'marketing'],
            ],
        ],
        'hardware_store' => [
            'team_title' => 'Equipo recomendado para ferreteria',
            'team_copy' => 'Una buena ferreteria escala cuando mostrador, inventario y cotizacion por proyecto tienen dueno.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Mostrador y cotizacion', 'copy' => 'Responde rapido y convierte necesidad en pedido util.', 'role' => Role::OPERATOR->value, 'when' => 'Desde que ya entran consultas por WhatsApp', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Control de inventario', 'copy' => 'Evita desorden entre rotacion, materiales y disponibilidad.', 'role' => Role::MANAGER->value, 'when' => 'Cuando el catalogo crece o ya manejas varias lineas', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Ventas por proyecto', 'copy' => 'Sostiene relacion con clientes frecuentes, obras y pedidos medianos.', 'role' => Role::ADMIN->value, 'when' => 'Cuando ya quieres vender por proyecto y no solo por urgencia', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para ferreteria',
            'automation_copy' => 'Conviene automatizar cotizacion, seguimiento y promociones utiles por temporada o necesidad.',
            'automation_playbook' => [
                ['eyebrow' => 'Cotizacion', 'title' => 'Seguimiento por obra o reparacion', 'copy' => 'Lleva cada consulta a precio, disponibilidad y siguiente paso.', 'trigger' => 'Lead nuevo o pedido sin cerrar', 'state' => 'retail_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Temporada', 'title' => 'Promociones de materiales clave', 'copy' => 'Mueve pintura, herramientas o combos segun la epoca y necesidad.', 'trigger' => 'Semana comercial o campaña estacional', 'state' => 'audience_ready', 'route_key' => 'marketing'],
                ['eyebrow' => 'Relacion', 'title' => 'Recordatorios a clientes frecuentes', 'copy' => 'Mantiene viva la recompra en mantenimiento y proyectos recurrentes.', 'trigger' => 'Clientes sin compra reciente', 'state' => 'followers', 'route_key' => 'marketing'],
            ],
        ],
        'premium_barbershop' => [
            'team_title' => 'Equipo recomendado para barber premium',
            'team_copy' => 'La experiencia se rompe rapido si agenda, recepcion y recompra dependen solo del barbero.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Recepcion y agenda', 'copy' => 'Organiza slots premium y protege la experiencia desde el primer mensaje.', 'role' => Role::OPERATOR->value, 'when' => 'Desde las primeras reservas por WhatsApp', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Lider de grooming', 'copy' => 'Cuida tiempos, servicios y calidad del ritual premium.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya se mezclan varios especialistas o bundles', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Retencion y memberships', 'copy' => 'Sostiene paquetes, recompra y campanas de frecuencia.', 'role' => Role::ADMIN->value, 'when' => 'Cuando ya quieres operar como marca premium', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para barber premium',
            'automation_copy' => 'Lo que suma aqui es llenar agenda premium, reducir huecos y activar recompra.',
            'automation_playbook' => [
                ['eyebrow' => 'Agenda', 'title' => 'Recordatorio de cita y no-show', 'copy' => 'Protege slots premium con mensajes y seguimiento oportuno.', 'trigger' => 'Reserva cercana o ausencia reciente', 'state' => 'booking_ready', 'route_key' => 'appointments'],
                ['eyebrow' => 'Retencion', 'title' => 'Recompra cada pocas semanas', 'copy' => 'Invita al siguiente corte o combo sin esperar a que el cliente desaparezca.', 'trigger' => 'Clientes sin visita reciente', 'state' => 'audience_ready', 'route_key' => 'marketing'],
                ['eyebrow' => 'Brand', 'title' => 'Promos y memberships', 'copy' => 'Empuja paquetes y rituales premium con mas consistencia.', 'trigger' => 'Campana mensual o lanzamiento', 'state' => 'pro_marketing', 'route_key' => 'marketing'],
            ],
        ],
        'spa_studio' => [
            'team_title' => 'Equipo recomendado para spa',
            'team_copy' => 'La promesa de bienestar necesita una operacion suave: recepcion, terapeutas y continuidad.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Recepcion y reservas', 'copy' => 'Coordina agenda, paquetes y mensajes previos a la sesion.', 'role' => Role::OPERATOR->value, 'when' => 'Desde la primera reserva recurrente', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Coordinacion de terapeutas', 'copy' => 'Ordena disponibilidad, cabinas y calidad de la experiencia.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya operas varias sesiones por dia', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Retencion y paquetes', 'copy' => 'Mueve bonos, membresias y continuidad mensual.', 'role' => Role::ADMIN->value, 'when' => 'Cuando la recompra ya pesa en ingresos', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para spa',
            'automation_copy' => 'Aqui la automatizacion debe ayudar a sostener continuidad y experiencia premium.',
            'automation_playbook' => [
                ['eyebrow' => 'Reserva', 'title' => 'Confirmacion y recordatorio', 'copy' => 'Reduce ausencias y prepara mejor al cliente antes de cada sesion.', 'trigger' => 'Cita creada o proxima', 'state' => 'booking_ready', 'route_key' => 'appointments'],
                ['eyebrow' => 'Packs', 'title' => 'Venta de bonos y paquetes', 'copy' => 'Convierte sesiones sueltas en continuidad mensual.', 'trigger' => 'Cliente atendido o base activa', 'state' => 'audience_ready', 'route_key' => 'coupons'],
                ['eyebrow' => 'Bienestar', 'title' => 'Campanas de reactivacion', 'copy' => 'Recupera clientes para faciales, masajes o rituales.', 'trigger' => 'Clientes sin visita reciente', 'state' => 'pro_marketing', 'route_key' => 'marketing'],
            ],
        ],
        'auto_parts_store' => [
            'team_title' => 'Equipo recomendado para autopartes',
            'team_copy' => 'Autopartes gana cuando venta tecnica, soporte y seguimiento comparten criterio.',
            'team_playbook' => [
                ['eyebrow' => 'Hoy', 'title' => 'Asesor tecnico comercial', 'copy' => 'Ayuda a validar compatibilidad y mueve el cierre con menos vueltas.', 'role' => Role::OPERATOR->value, 'when' => 'Desde el primer flujo de consultas tecnicas', 'route_key' => 'team'],
                ['eyebrow' => 'Siguiente', 'title' => 'Coordinacion de repuestos', 'copy' => 'Ordena inventario, alta rotacion y soporte tecnico.', 'role' => Role::MANAGER->value, 'when' => 'Cuando ya cruzas varias lineas y servicios', 'route_key' => 'team'],
                ['eyebrow' => 'Escala', 'title' => 'Seguimiento postventa', 'copy' => 'Sostiene recompra, mantenimientos y confianza en soluciones.', 'role' => Role::ADMIN->value, 'when' => 'Cuando ya quieres crecer relacion con talleres o clientes frecuentes', 'route_key' => 'team'],
            ],
            'automation_title' => 'Automatizaciones comerciales para autopartes',
            'automation_copy' => 'La automatizacion ideal distingue compra inmediata, consulta tecnica y recompra.',
            'automation_playbook' => [
                ['eyebrow' => 'Tecnico', 'title' => 'Seguimiento de compatibilidad', 'copy' => 'Responde mas rapido cuando el cliente aun duda si la pieza aplica.', 'trigger' => 'Consulta tecnica o lead nuevo', 'state' => 'hybrid_sales_base', 'route_key' => 'leads'],
                ['eyebrow' => 'Operacion', 'title' => 'Recordatorio de pedido o montaje', 'copy' => 'No dejes caer la venta entre compra, retiro y servicio.', 'trigger' => 'Pedido pendiente o servicio proximo', 'state' => 'mixed_activity', 'route_key' => 'orders'],
                ['eyebrow' => 'Retencion', 'title' => 'Campanas por mantenimiento', 'copy' => 'Mueve filtros, baterias y repuestos de alta recurrencia.', 'trigger' => 'Clientes o seguidores activos', 'state' => 'audience_ready', 'route_key' => 'marketing'],
            ],
        ],
    ],
];
