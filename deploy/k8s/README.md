# Mines Platform – Kubernetes Deployment Guide

## Prerequisites

- Kubernetes 1.28+ cluster (EKS, GKE, AKS, or self-hosted)
- `kubectl` configured with cluster access
- NGINX Ingress Controller installed
- cert-manager installed (for TLS)
- AWS EFS CSI driver (or equivalent RWX storage class)

## Files

| File | Purpose |
|------|---------|
| `namespace.yaml` | Creates the `mines` namespace |
| `deployment.yaml` | PHP-FPM + nginx web pod (2 replicas, rolling deploy) |
| `workers.yaml` | Queue worker, scheduler, and Reverb WebSocket pods |
| `service.yaml` | ClusterIP services for app + Reverb |
| `ingress.yaml` | NGINX Ingress with TLS (cert-manager) |
| `hpa.yaml` | Horizontal Pod Autoscaler (2–10 web pods, 2–6 queue pods) |
| `storage-config.yaml` | PVC (ReadWriteMany) + non-sensitive ConfigMap |

## Secrets

Create the `mines-secrets` Secret with sensitive values before deploying:

```bash
kubectl -n mines create secret generic mines-secrets \
  --from-literal=APP_KEY="base64:your-key-here" \
  --from-literal=DB_HOST="your-rds-endpoint" \
  --from-literal=DB_DATABASE="mines" \
  --from-literal=DB_USERNAME="mines_user" \
  --from-literal=DB_PASSWORD="your-db-password" \
  --from-literal=REDIS_HOST="your-elasticache-endpoint" \
  --from-literal=REDIS_PASSWORD="your-redis-password" \
  --from-literal=SENTRY_DSN="https://..." \
  --from-literal=PAYSTACK_SECRET_KEY="sk_live_..." \
  --from-literal=REVERB_APP_KEY="..." \
  --from-literal=REVERB_APP_SECRET="..."
```

## Deploy

```bash
# First deploy
kubectl apply -f deploy/k8s/namespace.yaml
kubectl apply -f deploy/k8s/storage-config.yaml
kubectl apply -f deploy/k8s/deployment.yaml
kubectl apply -f deploy/k8s/workers.yaml
kubectl apply -f deploy/k8s/service.yaml
kubectl apply -f deploy/k8s/ingress.yaml
kubectl apply -f deploy/k8s/hpa.yaml

# Run DB migrations (one-off job)
kubectl -n mines exec deploy/mines-app -- php artisan migrate --force

# Subsequent deploys (update image tag via CI/CD)
kubectl -n mines set image deployment/mines-app app=ghcr.io/sakhileb/mines:$TAG
kubectl -n mines set image deployment/mines-queue queue=ghcr.io/sakhileb/mines:$TAG
kubectl -n mines rollout status deployment/mines-app
```

## Scaling

The HPA will auto-scale `mines-app` from 2 to 10 pods based on CPU (70%) and memory (80%).
Queue workers scale from 2 to 6 pods based on CPU (80%).

## Storage Class

Replace `efs-sc` in `storage-config.yaml` with your cloud provider's RWX storage class:
- AWS EKS: `efs-sc` (EFS CSI driver)
- GKE: `standard-rwx` (Filestore)
- AKS: `azurefile`
- Self-hosted: `nfs-client` or `longhorn`
