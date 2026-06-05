# Despliegue en Kubernetes — RP Travels

Estos manifiestos despliegan la aplicación, MySQL y phpMyAdmin en un clúster
de Kubernetes. Criterio del módulo **Servicios de Red e Internet**
("Desplegar servicios en contenedores Docker con Kubernetes").

Para probarlo en local necesitas un clúster. La opción más sencilla es
**minikube** (o `k3s`). Los pasos siguientes asumen minikube.

## Requisitos
- Docker
- `kubectl`
- `minikube`

## Pasos

### 1. Arrancar el clúster
```bash
minikube start
```

### 2. Construir la imagen de la app DENTRO de minikube
Así Kubernetes encuentra la imagen sin necesidad de un registro externo:
```bash
eval $(minikube docker-env)        # apunta tu Docker al de minikube
docker build -t rp-travels-app:latest .   # desde la raíz del proyecto
```

### 3. Crear el namespace y el Secret
```bash
kubectl apply -f k8s/00-namespace.yaml
# Edita primero k8s/01-secret.yaml con tus contraseñas reales:
kubectl apply -f k8s/01-secret.yaml
```

### 4. Cargar el esquema completo como ConfigMap
MySQL ejecutará este archivo en su primer arranque (incluye tablas, triggers y usuarios):
```bash
kubectl -n rp-travels create configmap rp-db-init \
  --from-file=01-init.sql=sql/rp.sql
```

### 5. Desplegar todo
```bash
kubectl apply -f k8s/02-mysql.yaml
kubectl apply -f k8s/03-app.yaml
kubectl apply -f k8s/04-phpmyadmin.yaml
```

### 6. Comprobar el estado
```bash
kubectl -n rp-travels get pods,svc
```
Espera a que todos los pods estén `Running` y `READY`.

### 7. Abrir la aplicación
```bash
minikube service rp-app -n rp-travels --url          # URL de la web
minikube service rp-phpmyadmin -n rp-travels --url   # URL de phpMyAdmin
```
(O directamente `http://<IP-del-nodo>:30080` y `:30081`.)

## Notas
- `replicas: 2` en la app demuestra **alta disponibilidad**: si un pod cae,
  el otro sigue sirviendo y Kubernetes recrea el caído automáticamente.
- El archivo `sql/rp.sql` incluye todo en uno: tablas, datos iniciales,
  triggers de auditoría y usuarios de BD con sus privilegios.
- Para parar y limpiar todo: `kubectl delete namespace rp-travels`.
